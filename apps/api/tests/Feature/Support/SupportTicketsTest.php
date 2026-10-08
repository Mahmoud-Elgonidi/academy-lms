<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Billing\Actions\RecordPayment;
use Modules\Communication\Models\Notification;
use Modules\Evaluation\Queries\TeacherKpis;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Money\Money;
use Modules\Support\Actions\AssignTicket;
use Modules\Support\Actions\ClassifyTicket;
use Modules\Support\Actions\CloseTicket;
use Modules\Support\Actions\DecideComplaint;
use Modules\Support\Actions\FollowUpTickets;
use Modules\Support\Actions\OpenTicket;
use Modules\Support\Actions\ReopenTicket;
use Modules\Support\Actions\ReplyToTicket;
use Modules\Support\Actions\ResolveTicket;
use Modules\Support\Actions\SaveSupportTargets;
use Modules\Support\Enums\TicketKind;
use Modules\Support\Enums\TicketPriority;
use Modules\Support\Filament\Family\FamilyHelp;
use Modules\Support\Filament\Pages\TicketPage;
use Modules\Support\Filament\Pages\Tickets;
use Modules\Support\Filament\Teacher\MySupport;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;
use Modules\Support\Queries\TicketDetails;
use Modules\Support\SupportRules;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Support tickets and complaints (docs/architecture/01, section 16; 02,
 * section 12). The fixture: a London adult student whose guardian pays, with
 * a Cairo teacher on Tuesdays and Saturdays 18:00 Cairo from 6 October 2026;
 * an admin and a supervisor answer; an accountant records payments.
 */

beforeEach(function () {
    $this->withoutDefer();
    $this->enrolStudentOnPlan();
    app(RecordPayment::class)->handle($this->booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, null);
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->supervisor = User::factory()->withRole(Role::Supervisor)->create(['name' => 'Mona']);
    $this->accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->sales = User::factory()->withRole(Role::Sales)->create();
    Storage::fake('local');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function firstLesson(): Session
{
    return Session::query()->where('enrollment_id', test()->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
}

function complain(?string $sessionId = null, ?UploadedFile $file = null): Ticket
{
    return app(OpenTicket::class)->handle(test()->guardian->id, 'family', 'complaint', 'The teacher was late', 'The lesson started a quarter of an hour late.', test()->student->id, $sessionId ?? firstLesson()->id, $file);
}

function sent(string $userId, string $template): int
{
    return Notification::query()->where('user_id', $userId)->where('template_key', $template)->count();
}

it('takes a family\'s complaint about a lesson: the next number, high priority with its targets, the lesson\'s teacher, waiting for a decision', function () {
    CarbonImmutable::setTestNow('2026-10-07 09:00 UTC');
    $ticket = complain(file: UploadedFile::fake()->image('screen.png'));

    expect($ticket->number)->toBe(1)
        ->and($ticket->kind)->toBe(TicketKind::Complaint)
        ->and($ticket->priority)->toBe(TicketPriority::High)
        ->and($ticket->teacher_id)->toBe($this->teacher->id)
        ->and($ticket->complaint_status)->toBe('pending')
        ->and($ticket->respond_by->toIso8601String())->toBe('2026-10-07T13:00:00+00:00')
        ->and($ticket->resolve_by->toIso8601String())->toBe('2026-10-08T09:00:00+00:00')
        ->and($ticket->messages()->count())->toBe(1)
        ->and($ticket->messages()->first()->file_name)->toBe('screen.png');
    Storage::disk('local')->assertExists($ticket->messages()->first()->file_path);

    // Told to who oversees the teachers (owner, admin, supervisor), never to the teacher or the family.
    expect(sent($this->supervisor->id, 'support.ticket_opened'))->toBe(1)
        ->and(sent($this->admin->id, 'support.ticket_opened'))->toBe(1)
        ->and(sent($this->sales->id, 'support.ticket_opened'))->toBe(0)
        ->and(sent($this->teacher->id, 'support.ticket_opened'))->toBe(0)
        ->and(complain()->number)->toBe(2);

    // A bill goes to who records payments; a technical problem to everyone who answers tickets.
    app(OpenTicket::class)->handle($this->guardian->id, 'family', 'billing', 'Charged twice?', 'I see two payments for October.', $this->student->id);
    expect(sent($this->accountant->id, 'support.ticket_opened'))->toBe(1)->and(sent($this->sales->id, 'support.ticket_opened'))->toBe(0);
    app(OpenTicket::class)->handle($this->teacher->id, 'teacher', 'technical', 'My camera', 'The class does not see my camera.');
    expect(sent($this->sales->id, 'support.ticket_opened'))->toBe(1);
});

it('lets each person write only about their own: their learners and lessons, the kinds their look allows', function () {
    $open = app(OpenTicket::class);
    $stranger = User::factory()->withRole(Role::Guardian)->create();
    $otherStudent = User::factory()->withRole(Role::Student)->create();

    expect(fn () => $open->handle($stranger->id, 'family', 'other', 'Hello', 'A question', $this->student->id))->toThrow(ValidationException::class)
        ->and(fn () => $open->handle($this->teacher->id, 'teacher', 'complaint', 'About a teacher', 'Complaint', null))->toThrow(ValidationException::class)
        ->and(fn () => $open->handle($this->guardian->id, 'teacher', 'technical', 'Hello', 'Pretending to teach'))->toThrow(AuthorizationException::class);

    // A lesson of another student, or the teacher's own lesson for another teacher, cannot be linked.
    app(PeopleApi::class)->linkGuardian($this->guardian->id, $otherStudent->id);
    expect(fn () => $open->handle($this->guardian->id, 'family', 'other', 'Hello', 'Wrong lesson', $otherStudent->id, firstLesson()->id))->toThrow(ValidationException::class)
        ->and(fn () => $open->handle($this->other->id, 'teacher', 'technical', 'Hello', 'Not my lesson', null, firstLesson()->id))->toThrow(ValidationException::class)
        ->and($open->handle($this->teacher->id, 'teacher', 'technical', 'Mic', 'My microphone', null, firstLesson()->id)->session_id)->toBe(firstLesson()->id);

    // Money only in the classic look; a child in the kids' look opens nothing; children's ages decide the look.
    $rules = app(SupportRules::class);
    app(PeopleApi::class)->saveStudentProfile($otherStudent->id, CarbonImmutable::parse('2017-01-01'), null);
    $teen = User::factory()->withRole(Role::Student)->create();
    app(PeopleApi::class)->saveStudentProfile($teen->id, CarbonImmutable::parse('2010-01-01'), null);
    expect(array_map(fn ($k) => $k->value, $rules->kindsFor('family', $this->guardian->id)))->toBe(['complaint', 'billing', 'technical', 'other'])
        ->and(array_map(fn ($k) => $k->value, $rules->kindsFor('family', $teen->id)))->toBe(['complaint', 'technical', 'other'])
        ->and($rules->kindsFor('family', $otherStudent->id))->toBe([])
        ->and(array_map(fn ($k) => $k->value, $rules->kindsFor('teacher')))->toBe(['technical', 'billing', 'other'])
        ->and(fn () => $open->handle($teen->id, 'family', 'billing', 'Money', 'A refund', $teen->id))->toThrow(ValidationException::class);
});

it('turns the conversation: the academy\'s answer meets the first-answer target and waits for the family; notes stay inside', function () {
    CarbonImmutable::setTestNow('2026-10-07 09:00 UTC');
    $ticket = complain();
    $reply = app(ReplyToTicket::class);

    CarbonImmutable::setTestNow('2026-10-07 10:00 UTC');
    $reply->handle($ticket, $this->supervisor->id, 'He was sick and joined late. Notes for later: second time this month.', null, note: true);
    expect($ticket->refresh()->status->value)->toBe('open')->and($ticket->first_response_at)->toBeNull()
        ->and(sent($this->guardian->id, 'support.answered'))->toBe(0);

    $reply->handle($ticket, $this->supervisor->id, 'Sorry! We spoke to the teacher and will add the lost minutes.');
    $ticket->refresh();
    expect($ticket->status->value)->toBe('waiting')
        ->and($ticket->first_response_at->toIso8601String())->toBe('2026-10-07T10:00:00+00:00')
        ->and($ticket->last_side)->toBe('staff')
        ->and(sent($this->guardian->id, 'support.answered'))->toBe(1);

    $reply->handle($ticket, $this->guardian->id, 'Thank you.');
    expect($ticket->refresh()->status->value)->toBe('open')
        ->and(sent($this->supervisor->id, 'support.requester_replied'))->toBe(1);

    // The family sees the messages, never the note; nobody else may write on it, and a family cannot write a note.
    $theirs = app(TicketDetails::class)->of($ticket, forRequester: true)['messages'];
    expect(array_map(fn ($m) => $m->kind, $theirs))->toBe(['message', 'message', 'message'])
        ->and(count(app(TicketDetails::class)->of($ticket, forRequester: false)['messages']))->toBe(4)
        ->and(fn () => $reply->handle($ticket, $this->teacher->id, 'Me too'))->toThrow(AuthorizationException::class)
        ->and(fn () => $reply->handle($ticket, $this->guardian->id, 'Note', null, note: true))->toThrow(AuthorizationException::class);
});

it('keeps what was said: messages cannot be changed or deleted', function () {
    $message = complain()->messages()->first();

    expect(fn () => DB::table('ticket_messages')->where('id', $message->id)->update(['body' => 'changed']))->toThrow(QueryException::class);
});

it('confirms a complaint into the teacher\'s numbers, tells the teacher without the family, and logs it; dismissing it takes it out', function () {
    CarbonImmutable::setTestNow('2026-10-07 09:00 UTC');
    $ticket = complain();
    $decide = app(DecideComplaint::class);

    expect(fn () => app(ResolveTicket::class)->handle($ticket, $this->supervisor->id, 'Done'))->toThrow(ValidationException::class)   // decide first
        ->and(fn () => $decide->handle($ticket, true, 'He was late', $this->sales->id))->toThrow(AuthorizationException::class)
        ->and(fn () => $decide->handle($ticket, true, '', $this->supervisor->id))->toThrow(ValidationException::class);

    $decide->handle($ticket, true, 'Late twice this month; talked to him.', $this->supervisor->id);
    expect($ticket->refresh()->complaint_status)->toBe('validated')
        ->and(sent($this->teacher->id, 'support.complaint_validated'))->toBe(1)
        ->and(Notification::query()->where('user_id', $this->teacher->id)->where('template_key', 'support.complaint_validated')->value('body'))->not->toContain($this->guardian->name)
        ->and(DB::table('audit_logs')->where('action', 'support.complaint_validated')->count())->toBe(1);

    $kpi = fn () => app(TeacherKpis::class)->forTeachers([$this->teacher->id], CarbonImmutable::parse('2026-10-08 12:00 UTC'))[$this->teacher->id]['complaints'];
    // One taught lesson so far (the fixture's first was on the 6th): one confirmed complaint per lesson = 100 per 100.
    DB::table('sessions')->where('id', firstLesson()->id)->update(['status' => 'completed']);
    expect($kpi()['value'])->toBe(100.0)->and($kpi()['n'])->toBe(1)->and($kpi()['meets'])->toBeNull();   // too few lessons to judge

    $decide->handle($ticket, false, 'The family later said the clock was wrong.', $this->supervisor->id);
    expect($kpi()['value'])->toBe(0.0)
        ->and(DB::table('audit_logs')->where('action', 'support.complaint_dismissed')->count())->toBe(1)
        ->and(TicketMessage::query()->where('ticket_id', $ticket->id)->where('event', 'complaint')->count())->toBe(2);

    app(ResolveTicket::class)->handle($ticket, $this->supervisor->id, 'We looked into it, thank you.');
    expect($ticket->refresh()->status->value)->toBe('resolved')->and(sent($this->guardian->id, 'support.resolved'))->toBe(1);
});

it('reopens a resolved ticket within its week, closes it after a quiet week, and takes no more messages once closed', function () {
    CarbonImmutable::setTestNow('2026-10-07 09:00 UTC');
    $ticket = app(OpenTicket::class)->handle($this->guardian->id, 'family', 'technical', 'Class link', 'The class does not open.', $this->student->id);
    app(ResolveTicket::class)->handle($ticket, $this->admin->id, 'Fixed: try again.');

    CarbonImmutable::setTestNow('2026-10-09 09:00 UTC');
    app(ReopenTicket::class)->handle($ticket->refresh(), $this->guardian->id);
    expect($ticket->refresh()->status->value)->toBe('open');
    app(ResolveTicket::class)->handle($ticket, $this->admin->id);

    CarbonImmutable::setTestNow('2026-10-16 09:01 UTC');
    expect(fn () => app(ReopenTicket::class)->handle($ticket->refresh(), $this->guardian->id))->toThrow(ValidationException::class);
    app(FollowUpTickets::class)->handle();
    expect($ticket->refresh()->status->value)->toBe('closed')
        ->and(fn () => app(ReplyToTicket::class)->handle($ticket, $this->guardian->id, 'Hello?'))->toThrow(ValidationException::class);

    // The requester closes their own: anyone else cannot.
    $other = app(OpenTicket::class)->handle($this->guardian->id, 'family', 'other', 'Never mind', 'Asked by mistake.', $this->student->id);
    expect(fn () => app(CloseTicket::class)->handle($other, $this->admin->id))->toThrow(AuthorizationException::class);
    app(CloseTicket::class)->handle($other, $this->guardian->id);
    expect($other->refresh()->status->value)->toBe('closed');
});

it('raises a late first answer once, to whoever has the ticket', function () {
    CarbonImmutable::setTestNow('2026-10-07 09:00 UTC');
    $ticket = app(OpenTicket::class)->handle($this->guardian->id, 'family', 'technical', 'Class link', 'The class does not open.', $this->student->id);
    app(AssignTicket::class)->handle($ticket, $this->sales->id, $this->admin->id);
    expect(sent($this->sales->id, 'support.assigned'))->toBe(1)
        ->and(fn () => app(AssignTicket::class)->handle($ticket, $this->teacher->id, $this->admin->id))->toThrow(ValidationException::class);

    CarbonImmutable::setTestNow('2026-10-08 08:59 UTC');
    app(FollowUpTickets::class)->handle();
    expect(sent($this->sales->id, 'support.overdue'))->toBe(0);

    CarbonImmutable::setTestNow('2026-10-08 09:05 UTC');     // normal: 24 hours to the first answer
    app(FollowUpTickets::class)->handle();
    app(FollowUpTickets::class)->handle();
    expect(sent($this->sales->id, 'support.overdue'))->toBe(1)->and(sent($this->admin->id, 'support.overdue'))->toBe(0);
});

it('lets the academy say what a ticket really is, with targets that follow the priority', function () {
    CarbonImmutable::setTestNow('2026-10-07 09:00 UTC');
    $ticket = app(OpenTicket::class)->handle($this->guardian->id, 'family', 'technical', 'Teacher left early', 'He left after 20 minutes.', $this->student->id, firstLesson()->id);
    expect($ticket->priority)->toBe(TicketPriority::Normal)->and($ticket->complaint_status)->toBeNull();

    app(ClassifyTicket::class)->handle($ticket, TicketKind::Complaint, TicketPriority::Urgent, $this->admin->id);
    $ticket->refresh();
    expect($ticket->complaint_status)->toBe('pending')
        ->and($ticket->teacher_id)->toBe($this->teacher->id)
        ->and($ticket->respond_by->toIso8601String())->toBe('2026-10-07T10:00:00+00:00')
        ->and($ticket->resolve_by->toIso8601String())->toBe('2026-10-07T17:00:00+00:00');

    $teachers = app(OpenTicket::class)->handle($this->teacher->id, 'teacher', 'other', 'Hello', 'A question');
    expect(fn () => app(ClassifyTicket::class)->handle($teachers, TicketKind::Complaint, TicketPriority::Normal, $this->admin->id))->toThrow(ValidationException::class);
});

it('takes the academy\'s own targets for new tickets, checked and logged', function () {
    $targets = ['low' => ['respond' => 72, 'resolve' => 240], 'normal' => ['respond' => 8, 'resolve' => 48], 'high' => ['respond' => 2, 'resolve' => 12], 'urgent' => ['respond' => 1, 'resolve' => 4]];
    expect(fn () => app(SaveSupportTargets::class)->handle(['normal' => ['respond' => 10, 'resolve' => 5]] + $targets))->toThrow(ValidationException::class);
    app(SaveSupportTargets::class)->handle($targets);

    CarbonImmutable::setTestNow('2026-10-07 09:00 UTC');
    $ticket = app(OpenTicket::class)->handle($this->guardian->id, 'family', 'other', 'Hello', 'A question', $this->student->id);
    expect($ticket->respond_by->toIso8601String())->toBe('2026-10-07T17:00:00+00:00')
        ->and(DB::table('audit_logs')->where('action', 'support.targets_saved')->count())->toBe(1);
});

it('gives the apps the same tickets: what a new one can be about, writing one with a picture, the answers without the notes, and only one\'s own', function () {
    CarbonImmutable::setTestNow('2026-10-07 09:00 UTC');
    $api = fn (User $user) => $this->actingAs($user, 'sanctum')->withHeaders(['X-Academy' => 'noor', 'Accept-Language' => 'en', 'X-Timezone' => 'Africa/Cairo']);
    $lesson = firstLesson()->id;
    $stranger = User::factory()->withRole(Role::Guardian)->create();

    $new = $api($this->guardian)->getJson('/api/v1/support/new?learner_id='.$this->student->id)->assertOk()
        ->assertJsonPath('data.learner.id', $this->student->id)
        ->assertJsonPath('data.kinds.0.key', 'complaint');
    expect(collect($new->json('data.lessons'))->pluck('id'))->toContain($lesson);   // newest first: the next week's, then the past ones
    $api($this->teacher)->getJson('/api/v1/support/new')->assertOk()->assertJsonCount(3, 'data.kinds')->assertJsonPath('data.learner', null);

    $made = $api($this->guardian)->withHeaders(['Idempotency-Key' => 'ticket-1'])->post('/api/v1/support/tickets', [
        'learner_id' => $this->student->id, 'kind' => 'complaint', 'subject' => 'The teacher was late', 'body' => 'A quarter of an hour.',
        'session_id' => $lesson, 'file' => UploadedFile::fake()->image('late.png'),
    ], ['Accept' => 'application/json'])->assertCreated()
        ->assertJsonPath('data.reference', '#1')
        ->assertJsonPath('data.status.key', 'open')
        ->assertJsonPath('data.lesson.id', $lesson)
        ->assertJsonPath('data.messages.0.file.name', 'late.png');
    $id = $made->json('data.id');

    $this->actAsTenant($this->academy);
    $ticket = Ticket::query()->findOrFail($id);
    app(ReplyToTicket::class)->handle($ticket, $this->supervisor->id, 'Only for us.', null, note: true);
    app(ReplyToTicket::class)->handle($ticket, $this->supervisor->id, 'Sorry, we are on it.');

    $api($this->guardian)->getJson('/api/v1/support/tickets/'.$id)->assertOk()
        ->assertJsonPath('data.status.key', 'waiting')
        ->assertJsonPath('data.your_turn', true)
        ->assertJsonCount(2, 'data.messages')
        ->assertJsonPath('data.messages.1.side', 'staff')
        ->assertJsonPath('data.messages.1.author', 'Mona')
        ->assertDontSee('Only for us.');
    $api($this->guardian)->get($made->json('data.messages.0.file.url'))->assertOk();
    $api($this->guardian)->getJson('/api/v1/support/tickets')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);

    $api($this->guardian)->withHeaders(['Idempotency-Key' => 'reply-1'])->postJson('/api/v1/support/tickets/'.$id.'/messages', ['body' => 'Thank you.'])
        ->assertCreated()->assertJsonPath('data.status.key', 'open');
    $api($this->guardian)->postJson('/api/v1/support/tickets/'.$id.'/reopen')->assertUnprocessable();
    $api($this->guardian)->postJson('/api/v1/support/tickets/'.$id.'/close')->assertOk()->assertJsonPath('data.status.key', 'closed')->assertJsonPath('data.can_reply', false);

    // Nobody else's: another family, the teacher it is about.
    $api($this->teacher)->getJson('/api/v1/support/tickets/'.$id)->assertNotFound();
    $api($stranger)->getJson('/api/v1/support/tickets')->assertForbidden();
});

it('shows the academy its queue and a ticket, and lets it answer, note, decide and resolve from the screen', function () {
    CarbonImmutable::setTestNow('2026-10-07 09:00 UTC');
    $ticket = complain();
    $this->actingAs($this->supervisor);
    Filament::setCurrentPanel('academy');

    Livewire::test(Tickets::class)->assertSee($ticket->subject)->assertSee(__('support::tickets.queue.tabs.complaints'))
        ->set('tab', 'waiting')->assertDontSee($ticket->subject)
        ->set('tab', 'complaints')->assertSee($ticket->subject);

    Livewire::test(TicketPage::class, ['ticket' => $ticket->id])
        ->assertSee('The lesson started a quarter of an hour late.')
        ->set('mode', 'note')->set('body', 'Late twice now.')->call('send')
        ->set('mode', 'reply')->set('body', 'We are on it.')->call('send')
        ->assertSee('Late twice now.')->assertSee('We are on it.')
        ->callAction('decide', ['note' => 'Confirmed from the attendance.'], arguments: ['validated' => true])
        ->call('send', true);
    expect($ticket->refresh()->complaint_status)->toBe('validated')->and($ticket->status->value)->toBe('resolved');

    $this->actingAs($this->sales);
    Livewire::test(Tickets::class)->assertDontSee(__('support::tickets.queue.tabs.complaints'));
    $this->actingAs($this->teacher);
    $this->get('http://noor.lms.test/admin/support')->assertForbidden();
});

it('lets a family and a teacher write and follow their own in their areas, and keeps a child in the kids\' look out', function () {
    CarbonImmutable::setTestNow('2026-10-07 09:00 UTC');
    $this->actingAs($this->guardian);
    Filament::setCurrentPanel('family');
    Livewire::test(FamilyHelp::class)
        ->callAction('newTicket', ['kind' => 'technical', 'subject' => 'Class link', 'body' => 'It does not open.'])
        ->assertHasNoActionErrors()->assertSee('Class link');
    $ticket = Ticket::query()->where('requester_id', $this->guardian->id)->firstOrFail();
    app(ReplyToTicket::class)->handle($ticket, $this->admin->id, 'Fixed, try again.');
    Livewire::test(FamilyHelp::class)->call('show', $ticket->id)->assertSee('Fixed, try again.')
        ->set('body', 'It works now.')->call('sendReply')->assertSee('It works now.');

    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');
    Livewire::test(MySupport::class)->callAction('newTicket', ['kind' => 'billing', 'subject' => 'My statement', 'body' => 'October is missing a lesson.'])
        ->assertHasNoActionErrors()->assertSee('My statement')->assertDontSee('Class link');

    $child = User::factory()->withRole(Role::Student)->create();
    app(PeopleApi::class)->saveStudentProfile($child->id, CarbonImmutable::parse('2018-01-01'), null);
    expect(FamilyHelp::canAccess())->toBeFalse();
    $this->actingAs($child);
    expect(FamilyHelp::canAccess())->toBeFalse();
});
