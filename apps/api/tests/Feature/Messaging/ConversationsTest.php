<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Communication\Models\Notification;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Messaging\Actions\LockConversation;
use Modules\Messaging\Actions\MarkConversationRead;
use Modules\Messaging\Actions\OpenConversation;
use Modules\Messaging\Actions\OverseeConversation;
use Modules\Messaging\Actions\SaveMessagingSettings;
use Modules\Messaging\Actions\SendMessage;
use Modules\Messaging\Filament\Family\FamilyMessages;
use Modules\Messaging\Filament\Pages\Conversations;
use Modules\Messaging\Filament\Teacher\TeacherMessages;
use Modules\Messaging\Live\ConversationsChanged;
use Modules\Messaging\Live\MyMessagesChanged;
use Modules\Messaging\MessagingRules;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Models\ConversationMessage;
use Modules\Messaging\Privacy\MessagingData;
use Modules\Messaging\Queries\MyConversations;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Realtime\LiveSignals;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Messages between a teacher and a student on the platform only
 * (docs/architecture/02, section 17). The fixture: an adult London student
 * with a guardian, taught by a Cairo teacher; a second teacher who does not
 * teach them; the admin oversees and manages conversations, a supervisor
 * oversees only; another family is a stranger to them.
 */

beforeEach(function () {
    $this->withoutDefer();
    $this->enrolStudentOnPlan();
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->supervisor = User::factory()->withRole(Role::Supervisor)->create();
    $this->strangerChild = User::factory()->withRole(Role::Student)->create();
    $this->stranger = User::factory()->withRole(Role::Guardian)->create();
    app(PeopleApi::class)->linkGuardian($this->stranger->id, $this->strangerChild->id);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function talk(): Conversation
{
    return app(OpenConversation::class)->handle(test()->teacher->id, test()->teacher->id, test()->student->id);
}

function write(Conversation $c, User $author, string $body): ConversationMessage
{
    return app(SendMessage::class)->handle($c->refresh(), $author->id, $body);
}

it('lets a teacher write to a student they teach, hides a phone number before anyone reads it, and keeps the words as typed encrypted', function () {
    $c = talk();
    $m = write($c, $this->teacher, 'Well done today! Call me on 0100 123 4567 if you like.');

    expect($m->body)->toBe('Well done today! Call me on [•••] if you like.')
        ->and($m->hidden)->toBe(['phone'])
        ->and($m->side)->toBe('teacher')
        ->and($m->original)->toContain('0100 123 4567')
        ->and((string) DB::table('conversation_messages')->where('id', $m->id)->value('original'))->not->toContain('0100')
        ->and($c->refresh())->messages_count->toBe(1)->hidden_count->toBe(1)
        ->and($c->needsLook())->toBeTrue();
});

it('has the student\'s guardians in every conversation, and keeps everyone else out', function () {
    $c = talk();
    $rules = app(MessagingRules::class);
    expect($rules->sideOf($c, $this->guardian->id))->toBe('guardian')
        ->and($rules->members($c))->toEqualCanonicalizing([$this->teacher->id, $this->student->id, $this->guardian->id]);

    write($c, $this->guardian, 'Thank you, teacher.');
    expect(ConversationMessage::query()->where('author_id', $this->guardian->id)->value('side'))->toBe('guardian')
        // A teacher who does not teach them, another family, and the academy's staff write nothing here.
        ->and(fn () => app(OpenConversation::class)->handle($this->other->id, $this->other->id, $this->student->id))->toThrow(ValidationException::class)
        ->and(fn () => app(OpenConversation::class)->handle($this->stranger->id, $this->teacher->id, $this->student->id))->toThrow(AuthorizationException::class)
        ->and(fn () => write($c, $this->stranger, 'Hello'))->toThrow(AuthorizationException::class)
        ->and(fn () => write($c, $this->admin, 'Hello'))->toThrow(AuthorizationException::class);
});

it('keeps every message as it was written: the database refuses changes', function () {
    $m = write(talk(), $this->teacher, 'Hello');

    expect(fn () => DB::table('conversation_messages')->where('id', $m->id)->update(['body' => 'changed']))->toThrow(QueryException::class);
    expect(fn () => DB::table('conversation_messages')->where('id', $m->id)->delete())->toThrow(QueryException::class);
});

it('tells the others once until they read, and the academy once a day when something was hidden', function () {
    $c = talk();
    write($c, $this->teacher, 'Lesson notes: page 4.');
    write($c, $this->teacher, 'And my number is 01001234567');
    write($c, $this->teacher, 'Or mail me on a.b@gmail.com');

    $told = fn (User $u) => Notification::query()->where('user_id', $u->id)->where('template_key', 'messaging.new_message')->count();
    expect($told($this->student))->toBe(1)->and($told($this->guardian))->toBe(1)->and($told($this->teacher))->toBe(0);
    expect(Notification::query()->where('template_key', 'messaging.contact_hidden')->pluck('user_id')->all())
        ->toEqualCanonicalizing([$this->admin->id, $this->supervisor->id]);

    // Read, then a new message: told again.
    app(MarkConversationRead::class)->handle($c->refresh(), $this->student->id);
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(5));
    write($c, $this->teacher, 'See you Tuesday.');
    expect($told($this->student))->toBe(2)->and($told($this->guardian))->toBe(1);
});

it('counts what is unread for each person, a guardian across their children', function () {
    $c = talk();
    write($c, $this->teacher, 'Hello');
    write($c, $this->teacher, 'Ready for Tuesday?');
    $unread = app(MyConversations::class);

    expect($unread->unread($this->student->id))->toBe(2)->and($unread->unread($this->guardian->id))->toBe(2)->and($unread->unread($this->teacher->id))->toBe(0);
    app(MarkConversationRead::class)->handle($c->refresh(), $this->guardian->id);
    expect($unread->unread($this->guardian->id))->toBe(0)->and($unread->unread($this->student->id))->toBe(2);
});

it('turns read only when messages are off, the academy locks it, the teaching ended, or the teacher is kept from minors', function () {
    $c = talk();
    $rules = app(MessagingRules::class);
    expect($rules->whyNot($c))->toBeNull();

    app(SaveMessagingSettings::class)->handle(false);
    expect($rules->whyNot($c))->toBe('off')->and(fn () => write($c, $this->teacher, 'Hi'))->toThrow(ValidationException::class);
    app(SaveMessagingSettings::class)->handle(true);

    app(LockConversation::class)->lock($c->id, 'Looking into a hidden number.', $this->admin->id);
    expect($rules->whyNot($c->refresh()))->toBe('locked')->and(fn () => write($c, $this->guardian, 'Hi'))->toThrow(ValidationException::class);
    app(LockConversation::class)->unlock($c->id, $this->admin->id);

    // Kept from new lessons with minors: a student under 18 is out of reach, the history stays readable.
    app(PeopleApi::class)->saveStudentProfile($this->student->id, CarbonImmutable::parse('2014-05-01'), null);
    app(PeopleApi::class)->pauseForMinors($this->teacher->id, 'A concern is being looked into.', $this->admin->id);
    expect($rules->whyNot($c->refresh()))->toBe('paused');
    app(PeopleApi::class)->resumeForMinors($this->teacher->id, 'Closed.', $this->admin->id);
    app(PeopleApi::class)->saveStudentProfile($this->student->id, CarbonImmutable::parse('2004-05-01'), null);

    DB::table('enrollments')->update(['status' => 'ended']);
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addDays(200));
    expect($rules->whyNot($c->refresh()))->toBe('ended')
        ->and(fn () => app(OpenConversation::class)->handle($this->teacher->id, $this->teacher->id, $this->student->id))->not->toThrow(Throwable::class);
});

it('lets who oversees read a conversation and what was hidden, each opening logged; only managers pause one, the reason kept off the log', function () {
    $c = talk();
    $m = write($c, $this->teacher, 'WhatsApp me: 01001234567');

    expect(app(OverseeConversation::class)->open($c->id, $this->supervisor->id)->needsLook())->toBeFalse()
        ->and(app(OverseeConversation::class)->original($m->id, $this->supervisor->id))->toBe('WhatsApp me: 01001234567');
    $actions = DB::table('audit_logs')->where('subject_id', $c->id)->pluck('action')->all();
    expect($actions)->toContain('messaging.conversation_viewed', 'messaging.original_revealed');

    $this->actingAs($this->supervisor);
    Filament::setCurrentPanel('academy');
    Livewire::test(Conversations::class)
        ->call('show', $c->id)
        ->assertSee('[•••]')
        ->call('reveal', $m->id)
        ->assertSee('01001234567')
        ->assertActionHidden('lock');

    $this->actingAs($this->admin);
    Livewire::test(Conversations::class, ['conversation' => $c->id])
        ->callAction('lock', data: ['reason' => 'Asked a child for WhatsApp.'])
        ->assertHasNoActionErrors();
    expect($c->refresh()->isLocked())->toBeTrue()
        ->and(DB::table('audit_logs')->where('action', 'messaging.conversation_locked')->value('data'))->not->toContain('WhatsApp');

    $this->actingAs($this->teacher);
    expect(Conversations::canAccess())->toBeFalse();
});

it('shows a teacher\'s students and a family\'s teachers in their panels, and writes from there', function () {
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');
    Livewire::test(TeacherMessages::class)
        ->assertSee($this->student->name)
        ->call('pick', $this->student->id)
        ->set('body', 'Hello! See you on Tuesday.')
        ->call('send')
        ->assertSee('Hello! See you on Tuesday.');

    $this->actingAs($this->guardian);
    Filament::setCurrentPanel('family');
    $c = Conversation::query()->firstOrFail();
    Livewire::test(FamilyMessages::class, ['conversation' => $c->id])
        ->assertSet('with', $this->teacher->id)
        ->assertSee('Hello! See you on Tuesday.')
        ->set('body', 'my email is dad@example.com')
        ->call('send')
        ->assertSee('my email is [•••]');
    // Opening it read it.
    expect(app(MyConversations::class)->unread($this->guardian->id))->toBe(0);
});

it('serves the apps: the list, opening, writing once with a key, and nothing of another family', function () {
    $api = fn (User $user) => $this->actingAs($user, 'sanctum')->withHeaders(['X-Academy' => 'noor', 'Accept-Language' => 'en', 'X-Timezone' => 'Africa/Cairo']);

    $api($this->teacher)->getJson('/api/v1/messages/conversations')->assertOk()
        ->assertJsonPath('data.0.with.id', $this->student->id)->assertJsonPath('data.0.id', null);
    $id = $api($this->teacher)->postJson('/api/v1/messages/conversations', ['with_id' => $this->student->id])->assertCreated()->json('data.id');

    $send = fn () => $api($this->teacher)->withHeaders(['Idempotency-Key' => 'chat-1'])
        ->postJson("/api/v1/messages/conversations/{$id}/messages", ['body' => 'My number: 01001234567']);
    $send()->assertCreated()->assertJsonPath('data.body', 'My number: [•••]')->assertJsonPath('data.hidden', 'We hid a phone number: conversations stay here on the platform.');
    $send()->assertCreated();

    $family = $api($this->guardian)->getJson("/api/v1/messages/conversations/{$id}")->assertOk()
        ->assertJsonCount(1, 'data.messages') // the same key wrote once
        ->assertJsonPath('data.with.id', $this->teacher->id)->assertJsonPath('data.can_write', true)
        ->assertJsonPath('data.messages.0.side', 'teacher');
    // Names only: nobody's email or phone.
    expect($family->getContent())->not->toContain((string) $this->teacher->email)->not->toContain((string) $this->guardian->email);
    $api($this->guardian)->getJson('/api/v1/messages/unread')->assertOk()->assertJsonPath('data.count', 0);
    $api($this->student)->getJson('/api/v1/messages/unread')->assertOk()->assertJsonPath('data.count', 1);

    $api($this->stranger)->getJson("/api/v1/messages/conversations/{$id}")->assertNotFound();
    $api($this->stranger)->withHeaders(['Idempotency-Key' => 'chat-2'])->postJson("/api/v1/messages/conversations/{$id}/messages", ['body' => 'Hi'])->assertNotFound();
    $api($this->stranger)->postJson('/api/v1/messages/conversations', ['with_id' => $this->teacher->id, 'learner_id' => $this->student->id])->assertForbidden();
});

it('tells the teacher, the student and each guardian on their own channel, and the academy on its own', function () {
    $c = talk();
    config(['database.connections.chat_listener' => config('database.connections.pgsql')]);
    $pdo = DB::connection('chat_listener')->getPdo();
    $pdo->exec('listen lms_live');

    write($c, $this->teacher, 'Hello');
    $raised = [];
    while ($note = $pdo->pgsqlGetNotify(PDO::FETCH_ASSOC, 300)) {
        $raised[] = $note['payload'];
    }
    expect($raised)->toContain("messages:{$this->teacher->id}", "messages:{$this->student->id}", "messages:{$this->guardian->id}", "conversations:{$this->academy->id}");

    $signals = app(LiveSignals::class);
    expect($signals->event("messages:{$this->guardian->id}"))->toBeInstanceOf(MyMessagesChanged::class)
        ->and($signals->event("messages:{$this->guardian->id}")->broadcastAs())->toBe('messages.changed')
        ->and($signals->event("conversations:{$this->academy->id}"))->toBeInstanceOf(ConversationsChanged::class);
});

it('gives each person a copy of their conversations, a guardian their own words, and erases nothing', function () {
    $c = talk();
    write($c, $this->teacher, 'Hello');
    write($c, $this->guardian, 'Call me 01001234567');
    $source = app(MessagingData::class);

    $student = $source->export($this->student->id)->data['conversations'];
    expect($student)->toHaveCount(1)->and($student[0]['messages'])->toHaveCount(2);
    $guardian = $source->export($this->guardian->id)->data['conversations'][0]['messages'];
    expect($guardian)->toHaveCount(1)
        ->and($guardian[0]['text'])->toBe('Call me [•••]')
        ->and($guardian[0]['as_typed'])->toBe('Call me 01001234567')
        ->and($source->erase($this->student->id))->toBe([]);
});
