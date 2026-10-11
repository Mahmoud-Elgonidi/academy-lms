<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\ExtendSession;
use Modules\Scheduling\Actions\LogContactAttempt;
use Modules\Scheduling\Actions\MarkStudentAbsent;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Actions\ResolveReview;
use Modules\Scheduling\Actions\ScheduleSession;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Contracts\Events\SessionCancelled;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;

beforeEach(function () {
    // Monday 5 October 2026, noon: the lessons below are this evening, whatever day the tests run.
    CarbonImmutable::setTestNow('2026-10-05 12:00 UTC');
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->teacher = User::factory()->withRole(Role::Teacher)->create();
    $this->student = User::factory()->withRole(Role::Student)->create(['timezone' => 'Asia/Qatar']);
    $this->admin = User::factory()->withRole(Role::Admin)->create();
    $this->subject = app(CatalogApi::class)->createSubject('تجويد', 'Tajweed');
    $this->start = CarbonImmutable::parse('2026-10-05 19:00', 'UTC');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function book(string $start = '19:00', int $minutes = 45, ?string $teacherId = null, ?string $studentId = null): Session
{
    return app(ScheduleSession::class)->handle(new NewSession(
        teacherId: $teacherId ?? test()->teacher->id,
        studentId: $studentId ?? test()->student->id,
        subjectId: test()->subject,
        startsAt: CarbonImmutable::parse("2026-10-05 {$start}", 'UTC'),
        durationMin: $minutes,
        anchorTimezone: 'Africa/Cairo',
    ));
}

function joinAt(Session $session, User $user, string $time): void
{
    app(RecordParticipantJoin::class)->handle($session->refresh(), $user->id, CarbonImmutable::parse("2026-10-05 {$time}", 'UTC'));
}

function sweepAt(string $time): int
{
    return app(ApplyAttendanceDeadlines::class)->handle(CarbonImmutable::parse("2026-10-05 {$time}", 'UTC'));
}

it('refuses to double-book a teacher, in the database itself', function () {
    book('19:00', 45);
    $otherStudent = User::factory()->withRole(Role::Student)->create();

    expect(fn () => book('19:30', 30, studentId: $otherStudent->id))->toThrow(ValidationException::class)
        ->and(Session::query()->count())->toBe(1);
});

it('starts the session when the teacher joins', function () {
    $session = book();
    joinAt($session, $this->teacher, '18:59');

    expect($session->refresh()->status)->toBe(SessionStatus::InProgress);
});

it('records a teacher absence at T+10 when the student waited, with a makeup owed', function () {
    Event::fake([SessionFinalized::class]);
    $session = book();
    joinAt($session, $this->student, '18:59');

    expect(sweepAt('19:09'))->toBe(0);
    expect(sweepAt('19:10'))->toBe(1);

    $session->refresh();
    expect($session->status)->toBe(SessionStatus::TeacherAbsent)
        ->and($session->decision)->toMatchArray(['credit' => 'makeup', 'rule' => 9]);
    Event::assertDispatched(SessionFinalized::class, fn ($e) => $e->outcome === 'teacher_absent');
});

it('sends a session nobody joined to review rather than blaming anyone', function () {
    $session = book();
    sweepAt('19:10');

    expect($session->refresh()->status)->toBe(SessionStatus::NeedsReview)
        ->and($session->review_reason)->toBe('nobody_joined');
});

it('records a student absence after the wait: 15 minutes for a 45-minute session', function () {
    $session = book('19:00', 45);
    joinAt($session, $this->teacher, '18:58');

    sweepAt('19:14');
    expect($session->refresh()->status)->toBe(SessionStatus::InProgress);

    sweepAt('19:15');
    expect($session->refresh()->status)->toBe(SessionStatus::StudentAbsent);
});

it('completes a session ten minutes after it ends when 75% was taught, else sends it to review', function () {
    $full = book('17:00', 45);
    joinAt($full, $this->teacher, '16:59');
    joinAt($full, $this->student, '17:05');

    $otherStudent = User::factory()->withRole(Role::Student)->create();
    $otherTeacher = User::factory()->withRole(Role::Teacher)->create();
    $short = book('17:00', 40, $otherTeacher->id, $otherStudent->id);
    joinAt($short, $otherTeacher, '17:00');
    joinAt($short, $otherStudent, '17:12');

    sweepAt('17:55');

    expect($full->refresh()->status)->toBe(SessionStatus::Completed)
        ->and($full->decision['taught_min'])->toBe(40)
        ->and($short->refresh()->status)->toBe(SessionStatus::NeedsReview)
        ->and($short->review_reason)->toBe('short_delivery');
});

it('lets a coordinator mark the student absent early only when the teacher is in', function () {
    $session = book();
    expect(fn () => app(MarkStudentAbsent::class)->handle($session->load('participants'), $this->admin->id))
        ->toThrow(ValidationException::class);

    joinAt($session, $this->teacher, '18:59');
    app(MarkStudentAbsent::class)->handle($session->refresh()->load('participants'), $this->admin->id);

    expect($session->refresh()->status)->toBe(SessionStatus::StudentAbsent)
        ->and($session->decision['by'])->toBe('admin');
});

it('frees both people when a session is cancelled, so the hour can be booked again', function () {
    Event::fake([SessionCancelled::class]);
    $session = book();

    app(CancelSession::class)->handle($session, 'teacher', 'illness', 'called, no answer', $this->admin->id);

    expect($session->refresh()->status)->toBe(SessionStatus::Cancelled)
        ->and($session->decision)->toMatchArray(['by' => 'teacher', 'reason' => 'illness', 'credit' => 'makeup', 'rule' => 8])
        ->and(book()->exists)->toBeTrue();
    Event::assertDispatched(SessionCancelled::class, fn ($e) => $e->makeup && $e->sessionId === $session->id);
});

it('extends a session unless someone is booked right after', function () {
    $session = book('19:00', 30);
    joinAt($session, $this->teacher, '18:59');

    app(ExtendSession::class)->handle($session->refresh(), 5, $this->admin->id);
    expect($session->refresh()->ends_at->format('H:i'))->toBe('19:35');

    $otherStudent = User::factory()->withRole(Role::Student)->create();
    book('19:40', 30, studentId: $otherStudent->id);

    expect(fn () => app(ExtendSession::class)->handle($session->refresh(), 10, $this->admin->id))->toThrow(ValidationException::class)
        ->and($session->refresh()->ends_at->format('H:i'))->toBe('19:35');
});

it('lets a manager decide a review, and records the decision', function () {
    $session = book();
    sweepAt('19:10');

    app(ResolveReview::class)->handle($session->refresh(), 'cancelled_makeup', 'video provider outage', $this->admin->id);

    expect($session->refresh()->status)->toBe(SessionStatus::Cancelled)
        ->and($session->decision)->toMatchArray(['by' => 'admin', 'credit' => 'makeup', 'review_reason' => 'nobody_joined']);
});

it('keeps an append-only log of what happened', function () {
    $session = book();
    app(LogContactAttempt::class)->handle($session, 'teacher', 'running_late', null, $this->admin->id);

    expect(SessionEvent::query()->where('session_id', $session->id)->pluck('kind')->map->value->all())
        ->toBe(['scheduled', 'contact_logged'])
        ->and(fn () => SessionEvent::query()->where('session_id', $session->id)->update(['data' => '{}']))
        ->toThrow(QueryException::class);
});

it('never shows one academy the sessions of another', function () {
    book();
    $this->actAsTenant($this->createAcademy(['slug' => 'other']));

    expect(Session::query()->count())->toBe(0)
        ->and(DB::table('session_participants')->count())->toBe(0);
});
