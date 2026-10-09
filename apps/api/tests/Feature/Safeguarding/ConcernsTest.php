<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Classroom\Contracts\RecordingHolds;
use Modules\Communication\Models\Notification;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\DTOs\TeacherClearance;
use Modules\People\Contracts\PeopleApi;
use Modules\Safeguarding\Actions\AcknowledgeConcern;
use Modules\Safeguarding\Actions\AddConcernNote;
use Modules\Safeguarding\Actions\CloseConcern;
use Modules\Safeguarding\Actions\FollowUpConcerns;
use Modules\Safeguarding\Actions\PauseConcernTeacher;
use Modules\Safeguarding\Actions\ReopenConcern;
use Modules\Safeguarding\Actions\ReportConcern;
use Modules\Safeguarding\Filament\Family\TellUs;
use Modules\Safeguarding\Filament\Pages\Safeguarding;
use Modules\Safeguarding\Filament\Teacher\RaiseConcern;
use Modules\Safeguarding\Models\Concern;
use Modules\Safeguarding\Privacy\ConcernsData;
use Modules\Safeguarding\Queries\MyConcerns;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Concerns about a child's safety (docs/architecture/02, section 15). The
 * fixture: an adult London student whose guardian pays, taught by a Cairo
 * teacher; the admin is the academy's safeguarding lead. A ten-year-old with
 * their own parent joins them; a supervisor works at the academy but is not
 * a lead.
 */

beforeEach(function () {
    $this->withoutDefer();
    $this->enrolStudentOnPlan();
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
    $this->child = User::factory()->withRole(Role::Student)->create(['name' => 'Yousef']);
    $this->parent = User::factory()->withRole(Role::Guardian)->create();
    app(PeopleApi::class)->saveStudentProfile($this->child->id, CarbonImmutable::parse('2016-03-01'), null);
    app(PeopleApi::class)->linkGuardian($this->parent->id, $this->child->id);
    $this->supervisor = User::factory()->withRole(Role::Supervisor)->create();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function pastLesson(): Session
{
    $lesson = Session::query()->where('enrollment_id', test()->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
    CarbonImmutable::setTestNow($lesson->ends_at->addHours(2));

    return $lesson;
}

function raise(string $reporterId, string $side, ?string $studentId, string $category = 'outside_contact', ?string $body = 'The teacher asked for my phone number.', ?string $sessionId = null): Concern
{
    return app(ReportConcern::class)->handle($reporterId, $side, $studentId, $category, $body, false, $sessionId);
}

it('takes a guardian\'s concern about their child\'s lesson: numbered, the lesson\'s teacher, encrypted, the leads told without names, the recording held', function () {
    $lesson = pastLesson();
    $concern = raise($this->guardian->id, 'family', $this->student->id, sessionId: $lesson->id);

    expect($concern)->number->toBe(1)->reporter_side->toBe('guardian')->teacher_id->toBe($this->teacher->id)->session_id->toBe($lesson->id)
        ->and($concern->status->value)->toBe('new')
        ->and((string) DB::table('safeguarding_concerns')->where('id', $concern->id)->value('body'))->not->toContain('phone number');
    $told = Notification::query()->where('template_key', 'safeguarding.concern_reported')->get();
    expect($told->pluck('user_id')->all())->toBe([$this->admin->id])
        ->and(json_encode($told->first()->toArray()))->not->toContain($this->student->name)->not->toContain('phone number')
        ->and(app(RecordingHolds::class)->heldSessions([$lesson->id]))->toHaveKey($lesson->id);
    // Nobody but the leads hears: not the teacher, not other staff.
    expect(Notification::query()->whereIn('user_id', [$this->teacher->id, $this->supervisor->id])->where('template_key', 'like', 'safeguarding.%')->count())->toBe(0)
        ->and(DB::table('audit_logs')->where('action', 'safeguarding.concern_reported')->value('data'))->not->toContain($this->student->name);
});

it('lets a child in the kids\' look just press the button, in a child\'s words, and keeps families and teachers to their own', function () {
    expect(raise($this->child->id, 'family', $this->child->id, 'bullying', null))->reporter_side->toBe('student')
        // The kids' list has no "unsuitable content" item, and nobody writes about another family's child.
        ->and(fn () => raise($this->child->id, 'family', $this->child->id, 'content'))->toThrow(ValidationException::class)
        ->and(fn () => raise($this->guardian->id, 'family', $this->child->id))->toThrow(AuthorizationException::class)
        // An adult must say what happened.
        ->and(fn () => raise($this->guardian->id, 'family', $this->student->id, 'other', ''))->toThrow(ValidationException::class);

    // A teacher: about a student they teach, never another.
    expect(raise($this->teacher->id, 'teacher', $this->student->id, 'wellbeing', 'He said he is afraid to go home.'))->reporter_side->toBe('teacher')->teacher_id->toBeNull()
        ->and(fn () => raise($this->teacher->id, 'teacher', $this->child->id, 'wellbeing', 'Not my student.'))->toThrow(ValidationException::class)
        ->and(fn () => raise($this->supervisor->id, 'teacher', null, 'wellbeing', 'Not a teacher.'))->toThrow(AuthorizationException::class)
        ->and(fn () => raise($this->supervisor->id, 'staff', $this->child->id, 'wellbeing', 'Not a lead.'))->toThrow(AuthorizationException::class);
});

it('is taken, noted and closed by a lead; the reporter reads where it stands and the reply, never the notes or outcome', function () {
    $concern = raise($this->guardian->id, 'family', $this->student->id);
    app(AcknowledgeConcern::class)->handle($concern->id, $this->admin->id);
    app(AddConcernNote::class)->handle($concern->id, 'Called the guardian; the teacher denies it.', $this->admin->id);
    expect(app(MyConcerns::class)->of($this->guardian->id)[0])->status->toBe(['key' => 'open', 'label' => __('safeguarding::concerns.reporter_status.open')]);

    expect(fn () => app(CloseConcern::class)->handle($concern->id, 'resolved', null, '', $this->admin->id))->toThrow(ValidationException::class);
    app(CloseConcern::class)->handle($concern->id, 'resolved', 'Thank you. We spoke to the teacher and moved the lessons.', 'Teacher warned; lessons moved to another teacher.', $this->admin->id);

    $mine = app(MyConcerns::class)->of($this->guardian->id)[0];
    expect($mine['status']['key'])->toBe('closed')->and($mine['reply'])->toBe('Thank you. We spoke to the teacher and moved the lessons.')
        ->and(json_encode($mine))->not->toContain('Called the guardian')->not->toContain('resolved')
        ->and(Notification::query()->where('user_id', $this->guardian->id)->where('template_key', 'safeguarding.concern_answered')->count())->toBe(1)
        ->and(DB::table('audit_logs')->where('action', 'safeguarding.concern_closed')->count())->toBe(1);

    // Closed: no more notes until a lead opens it again.
    expect(fn () => app(AddConcernNote::class)->handle($concern->id, 'More.', $this->admin->id))->toThrow(ValidationException::class);
    app(ReopenConcern::class)->handle($concern->id, 'The family wrote again.', $this->admin->id);
    expect($concern->fresh())->status->value->toBe('open')->outcome->toBeNull()
        // The record stays: the app cannot rewrite its steps or delete it.
        ->and(fn () => DB::table('concern_entries')->where('concern_id', $concern->id)->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('safeguarding_concerns')->where('id', $concern->id)->delete())->toThrow(QueryException::class);
});

it('tells the leads again, once, when nobody took a concern within the hour', function () {
    CarbonImmutable::setTestNow('2026-10-07 09:00 UTC');
    $concern = raise($this->guardian->id, 'family', $this->student->id);

    CarbonImmutable::setTestNow('2026-10-07 09:59 UTC');
    expect(app(FollowUpConcerns::class)->handle())->toBe(0);
    CarbonImmutable::setTestNow('2026-10-07 10:01 UTC');
    expect(app(FollowUpConcerns::class)->handle())->toBe(1)
        ->and(app(FollowUpConcerns::class)->handle())->toBe(0)
        ->and(Notification::query()->where('user_id', $this->admin->id)->where('template_key', 'safeguarding.concern_waiting')->count())->toBe(1)
        ->and($concern->fresh()->escalated_at)->not->toBeNull();
});

it('pauses the teacher a concern is about from new lessons with minors, and lets them back', function () {
    $concern = raise($this->guardian->id, 'family', $this->student->id, sessionId: pastLesson()->id);
    app(PauseConcernTeacher::class)->pause($concern->id, 'While we look into it', $this->admin->id);
    expect(app(PeopleApi::class)->clearances([$this->teacher->id])[$this->teacher->id]->state)->toBe(TeacherClearance::PAUSED)
        ->and(app(PeopleApi::class)->mayTeach($this->teacher->id, $this->child->id))->toBeFalse()
        ->and(app(PeopleApi::class)->mayTeach($this->teacher->id, $this->student->id))->toBeTrue();

    app(PauseConcernTeacher::class)->resume($concern->id, 'Looked into: nothing found', $this->admin->id);
    expect(app(PeopleApi::class)->mayTeach($this->teacher->id, $this->child->id))->toBeTrue()
        ->and($concern->entries()->pluck('event')->filter()->values()->all())->toBe(['reported', 'teacher_paused', 'teacher_resumed']);
});

it('puts the concerns someone raised in their copy, and waits to erase anyone with one open', function () {
    $concern = raise($this->guardian->id, 'family', $this->student->id);
    expect(app(ConcernsData::class)->export($this->guardian->id)->data['concerns_raised'][0])->text->toBe('The teacher asked for my phone number.')
        // The student it is about does not get the guardian's words in their copy.
        ->and(app(ConcernsData::class)->export($this->student->id)->data['concerns_raised'])->toBe([])
        ->and(app(ConcernsData::class)->blockers($this->student->id))->not->toBe([])
        ->and(app(ConcernsData::class)->blockers($this->teacher->id))->toBe([]);

    app(CloseConcern::class)->handle($concern->id, 'no_concern', null, 'Looked into with the family.', $this->admin->id);
    expect(app(ConcernsData::class)->blockers($this->student->id))->toBe([]);
});

it('lets a guardian, a child in the kids\' look and a teacher raise one from their area, and a lead work it from the board', function () {
    $lesson = pastLesson();

    $this->actingAs($this->guardian);
    Filament::setCurrentPanel('family');
    Livewire::test(TellUs::class)
        ->assertSee(__('safeguarding::screens.family.danger.classic'))
        ->set('category', 'outside_contact')->set('text', '')->call('send')->assertHasErrors('body')
        ->set('text', 'He asked her to message him on WhatsApp.')->set('lesson', $lesson->id)->call('send')
        ->assertHasNoErrors()->assertSee(__('safeguarding::screens.family.sent.classic'))->assertSee('He asked her to message him on WhatsApp.');

    $this->actingAs($this->child);
    expect(TellUs::canAccess())->toBeTrue();
    Livewire::test(TellUs::class)
        ->assertSee(__('safeguarding::concerns.categories.kids.bullying'))->assertDontSee(__('safeguarding::screens.form.urgent'))
        ->set('category', 'bullying')->set('urgent', true)->call('send')->assertHasNoErrors()
        ->assertSee(__('safeguarding::screens.family.sent.kids'));
    // A child cannot mark it urgent, and need not write a word.
    expect(Concern::query()->where('reporter_id', $this->child->id)->first())->urgent->toBeFalse()->body->toBe('');

    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');
    Livewire::test(RaiseConcern::class)
        ->assertSee($this->student->name)->assertDontSee($this->child->name)
        ->set('category', 'wellbeing')->set('student', $this->student->id)->set('text', 'She cried and said she is scared at home.')->set('urgent', true)->call('send')
        ->assertHasNoErrors();
    expect(Concern::query()->where('reporter_id', $this->teacher->id)->first())->urgent->toBeTrue()->student_id->toBe($this->student->id);

    $guardians = Concern::query()->where('reporter_id', $this->guardian->id)->firstOrFail();
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    Livewire::test(Safeguarding::class, ['concern' => $guardians->id])
        ->assertSee($guardians->reference())->assertSee('He asked her to message him on WhatsApp.')
        ->callAction('take')
        ->callAction('note', ['body' => 'Spoke to the guardian.'])->assertSee('Spoke to the guardian.')
        ->callAction('pause', ['reason' => 'While we look into it'])
        ->callAction('close', ['outcome' => 'teacher_action', 'note' => 'Teacher warned in writing.', 'reply' => 'Thank you, we acted on it.'])
        ->assertHasNoActionErrors();
    expect($guardians->fresh())->status->value->toBe('closed')->reporter_reply->toBe('Thank you, we acted on it.')
        ->and(app(PeopleApi::class)->clearances([$this->teacher->id])[$this->teacher->id]->state)->toBe(TeacherClearance::PAUSED);

    // Nobody else at the academy opens the board.
    $this->actingAs($this->supervisor);
    expect(Safeguarding::canAccess())->toBeFalse();
    $this->get('http://noor.lms.test/admin/safeguarding')->assertForbidden();
});

it('lets the apps raise a concern and follow it, each in their own words and only their own', function () {
    $lesson = pastLesson();
    $api = fn (User $user) => $this->actingAs($user, 'sanctum')->withHeaders(['X-Academy' => 'noor', 'Accept-Language' => 'en', 'X-Timezone' => 'Africa/Cairo']);

    $api($this->guardian)->getJson('/api/v1/safeguarding/new?learner_id='.$this->student->id)->assertOk()
        ->assertJsonPath('data.look', 'classic')->assertJsonPath('data.text_required', true)->assertJsonPath('data.lessons.0.id', $lesson->id)
        ->assertJsonPath('data.danger', __('safeguarding::screens.family.danger.classic'));
    $api($this->child)->getJson('/api/v1/safeguarding/new')->assertOk()
        ->assertJsonPath('data.look', 'kids')->assertJsonPath('data.urgent_allowed', false)->assertJsonPath('data.lessons', [])
        ->assertJsonCount(4, 'data.categories')->assertJsonPath('data.categories.0.label', __('safeguarding::concerns.categories.kids.adult_behaviour'));
    $api($this->teacher)->getJson('/api/v1/safeguarding/new')->assertOk()
        ->assertJsonPath('data.look', 'teacher')->assertJsonPath('data.students.0.id', $this->student->id);

    $api($this->guardian)->withHeaders(['Idempotency-Key' => 'concern-1'])->postJson('/api/v1/safeguarding/concerns', [
        'learner_id' => $this->student->id, 'category' => 'outside_contact', 'text' => 'He asked for her Instagram.', 'urgent' => true, 'session_id' => $lesson->id,
    ])->assertCreated()->assertJsonPath('data.number', '#1')->assertJsonPath('data.status.key', 'new')->assertJsonPath('data.urgent', true);
    // Sent twice by a shaky connection: still one.
    $api($this->guardian)->withHeaders(['Idempotency-Key' => 'concern-1'])->postJson('/api/v1/safeguarding/concerns', [
        'learner_id' => $this->student->id, 'category' => 'outside_contact', 'text' => 'He asked for her Instagram.', 'urgent' => true, 'session_id' => $lesson->id,
    ])->assertCreated();
    $api($this->child)->withHeaders(['Idempotency-Key' => 'concern-2'])->postJson('/api/v1/safeguarding/concerns', ['category' => 'bullying', 'urgent' => true])
        ->assertCreated()->assertJsonPath('data.urgent', false);
    $api($this->guardian)->withHeaders(['Idempotency-Key' => 'concern-3'])->postJson('/api/v1/safeguarding/concerns', ['learner_id' => $this->child->id, 'category' => 'other', 'text' => 'Not mine.'])
        ->assertForbidden();
    $api($this->guardian)->withHeaders(['Idempotency-Key' => 'concern-4'])->postJson('/api/v1/safeguarding/concerns', ['learner_id' => $this->student->id, 'category' => 'other'])
        ->assertUnprocessable();

    $this->actAsTenant($this->academy);
    expect(Concern::query()->count())->toBe(2);
    $api($this->guardian)->getJson('/api/v1/safeguarding/concerns')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.text', 'He asked for her Instagram.');
    $api($this->teacher)->getJson('/api/v1/safeguarding/concerns')->assertOk()->assertJsonCount(0, 'data');
    $api($this->supervisor)->getJson('/api/v1/safeguarding/new')->assertForbidden();
});
