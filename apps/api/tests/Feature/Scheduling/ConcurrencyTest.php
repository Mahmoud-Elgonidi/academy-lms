<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Credits\Contracts\Events\CreditsGranted;
use Modules\Credits\Contracts\LotSource;
use Modules\Credits\Models\CreditLot;
use Modules\Credits\Models\CreditReservation;
use Modules\Enrollment\Models\Enrollment;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\BookExtraLesson;
use Modules\Scheduling\Actions\BookMakeup;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\CancelUnpaidSessions;
use Modules\Scheduling\Actions\ConfirmBooking;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\FlagForReview;
use Modules\Scheduling\Actions\FundSessions;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Actions\ResolveReview;
use Modules\Scheduling\Actions\ScheduleSession;
use Modules\Scheduling\Actions\TimeOff\AssignSubstitutes;
use Modules\Scheduling\Actions\TimeOff\DecideTimeOff;
use Modules\Scheduling\Actions\TimeOff\RequestTimeOff;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Contracts\Events\SessionCancelled;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\LessonChanged;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\SharedKernel\Money\Money;
use Tests\Support\EnrolledStudent;
use Tests\Support\RivalConnection;

uses(EnrolledStudent::class, RivalConnection::class);

/*
 * Two people (or a person and a sweeper) at the same lesson at the same
 * moment, against the real database: a second connection plays the other
 * side and commits in the gap between this side's check and its write
 * (docs/architecture/02, section 2, "منع الحجز المزدوج"). One side wins; the
 * other gets a clean validation error, never two lessons or two outcomes.
 * The fixture: Monday 5 October 2026, a Cairo teacher on shift Tuesdays
 * and Saturdays 17:00-21:00 Cairo, a London student's lessons at 18:00.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->second = User::factory()->withRole(Role::Student)->create(['timezone' => 'Asia/Riyadh']);
    app(PeopleApi::class)->saveStudentProfile($this->second->id, CarbonImmutable::parse('2001-03-01'), null);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function payTheBlock(): void
{
    app(RecordPayment::class)->handle(test()->booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, null);
}

function racedFirstLesson(): Session
{
    return Session::query()->where('enrollment_id', test()->booked->enrollmentId)->orderBy('starts_at')->firstOrFail();
}

/** Lessons of a teacher that hold a moment (live ones). */
function teacherLessonsAt(string $teacherId, CarbonImmutable $at): int
{
    return DB::table('session_participants')->where('user_id', $teacherId)->where('is_active', true)
        ->whereRaw('block_period @> ?::timestamptz', [$at->utc()->toIso8601String()])->count();
}

it('books a makeup on a time the other coordinator took a moment ago: one lesson, a clean refusal, the credit untouched', function () {
    payTheBlock();
    app(CancelSession::class)->handle(racedFirstLesson(), 'teacher', 'illness', null, $this->teacher->id);   // 32 hours ahead: makeup owed
    $at = CarbonImmutable::parse('2026-10-06 19:00', 'Africa/Cairo');

    $this->rivalCommitsBefore('insert into "session_participants"', fn () => $this->rivalBooks($this->teacher->id, $this->second->id, $this->quran, $at));

    expect(fn () => app(BookMakeup::class)->handle($this->booked->enrollmentId, $at, $this->admin->id))->toThrow(ValidationException::class)
        ->and(teacherLessonsAt($this->teacher->id, $at->addMinute()))->toBe(1)
        ->and(Session::query()->where('type', 'makeup')->count())->toBe(0)
        ->and((int) CreditLot::query()->where('source', 'makeup')->sum('remaining_min'))->toBe(30);
});

it('books an extra lesson, and a free trial, on a time just taken: refused cleanly, never two lessons', function () {
    payTheBlock();
    $extraAt = CarbonImmutable::parse('2026-10-06 19:30', 'Africa/Cairo');
    $this->rivalCommitsBefore('insert into "session_participants"', fn () => $this->rivalBooks($this->teacher->id, $this->second->id, $this->quran, $extraAt));

    expect(fn () => app(BookExtraLesson::class)->handle($this->booked->enrollmentId, $extraAt, $this->admin->id))->toThrow(ValidationException::class)
        ->and(teacherLessonsAt($this->teacher->id, $extraAt->addMinute()))->toBe(1)
        ->and(Session::query()->where('type', 'extra')->count())->toBe(0);

    $trialAt = CarbonImmutable::parse('2026-10-10 20:00', 'Africa/Cairo');
    $third = User::factory()->withRole(Role::Student)->create();
    app(PeopleApi::class)->saveStudentProfile($third->id, CarbonImmutable::parse('1999-01-01'), null);
    $hold = app(PlaceBookingHold::class)->handle($this->teacher->id, $third->id, [$trialAt->getTimestamp()], 30, $this->admin->id);
    $this->rivalCommitsBefore('insert into "session_participants"', fn () => $this->rivalBooks($this->teacher->id, $this->second->id, $this->quran, $trialAt));

    expect(fn () => app(ConfirmBooking::class)->trial($hold, $third->id, $this->quran, $this->admin->id))->toThrow(ValidationException::class)
        ->and(teacherLessonsAt($this->teacher->id, $trialAt->addMinute()))->toBe(1)
        ->and(Session::query()->where('type', 'trial')->count())->toBe(0);
});

it('enrols from Find a teacher on weekly times another enrolment took meanwhile: nothing of it is left behind', function () {
    $times = [];
    foreach (['2026-10-06', '2026-10-10'] as $first) {
        for ($i = 0; $i < 8; $i++) {
            $times[] = CarbonImmutable::parse("{$first} 19:00", 'Africa/Cairo')->addWeeks($i)->getTimestamp();
        }
    }
    $hold = app(PlaceBookingHold::class)->handle($this->teacher->id, $this->second->id, $times, 30, $this->admin->id);
    $enrolments = Enrollment::query()->count();

    // The other coordinator's weekly Tuesday 19:00 lands first.
    $this->rivalCommitsBefore('insert into "recurring_slots"', fn () => $this->rival()->insert(
        "insert into recurring_slots (id, enrollment_id, teacher_id, student_id, weekday, local_start, duration_min, anchor_tz, minute_of_week, effective, created_at, updated_at)
         values (?, ?, ?, ?, 2, '19:00', 30, 'Africa/Cairo', '{[2580,2610)}', '[2026-10-06,)', now(), now())",
        [(string) Str::uuid(), $this->booked->enrollmentId, $this->teacher->id, $this->student->id],
    ));

    expect(fn () => app(EnrollStudent::class)->handle($hold, $this->second->id, $this->quran, new EnrollmentTerms(Plan::query()->value('id'), $this->second->id, PriceRegion::query()->value('id')), $this->admin->id))
        ->toThrow(ValidationException::class)
        ->and(Enrollment::query()->count())->toBe($enrolments)
        ->and(DB::table('session_participants')->where('user_id', $this->second->id)->count())->toBe(0)
        ->and(DB::table('invoices')->count())->toBe(1);
});

it('refuses a coordinator\'s cancel of a lesson the sweeper closed meanwhile: one outcome, one credit move', function () {
    payTheBlock();
    $lesson = racedFirstLesson();
    CarbonImmutable::setTestNow('2026-10-06 15:01 UTC');
    app(RecordParticipantJoin::class)->handle($lesson, $this->student->id);
    $onScreen = $lesson->fresh();      // the coordinator opened it, the teacher still not in

    CarbonImmutable::setTestNow('2026-10-06 15:10 UTC');
    expect(app(ApplyAttendanceDeadlines::class)->handle())->toBe(1);
    Event::fake([SessionCancelled::class, SessionFinalized::class]);

    expect(fn () => app(CancelSession::class)->handle($onScreen, 'academy', 'other', null, $this->admin->id))->toThrow(LessonChanged::class)
        ->and($lesson->refresh()->status)->toBe(SessionStatus::TeacherAbsent)
        ->and($lesson->decision['rule'])->toBe(9)
        ->and(CreditLot::query()->where('source', 'makeup')->where('source_id', $lesson->id)->count())->toBe(1)
        ->and(DB::table('session_events')->where('session_id', $lesson->id)->where('kind', 'status_changed')->count())->toBe(1);
    Event::assertNotDispatched(SessionCancelled::class);
    Event::assertNotDispatched(SessionFinalized::class);
});

it('lets one of two managers decide a review: the other is told the lesson changed', function () {
    payTheBlock();
    $lesson = racedFirstLesson();
    CarbonImmutable::setTestNow('2026-10-06 15:00 UTC');
    app(RecordParticipantJoin::class)->handle($lesson, $this->teacher->id);
    app(RecordParticipantJoin::class)->handle($lesson->refresh(), $this->student->id);
    app(FlagForReview::class)->handle($lesson->refresh(), 'technical', 'the sound kept cutting', $this->admin->id);
    [$mine, $theirs] = [Session::query()->findOrFail($lesson->id), Session::query()->findOrFail($lesson->id)];
    Event::fake([SessionFinalized::class, SessionCancelled::class]);

    app(ResolveReview::class)->handle($mine, 'completed', null, $this->admin->id);

    expect(fn () => app(ResolveReview::class)->handle($theirs, 'teacher_absent', null, $this->admin->id))->toThrow(LessonChanged::class)
        ->and($lesson->refresh()->status)->toBe(SessionStatus::Completed)
        ->and(CreditReservation::query()->where('session_id', $lesson->id)->value('status'))->toBe('consumed')
        ->and(CreditLot::query()->where('source', 'makeup')->count())->toBe(0);
    Event::assertDispatchedTimes(SessionFinalized::class, 1);
});

it('leaves to the other side a lesson it changed while the sweeper ran, and goes on with the rest', function () {
    payTheBlock();
    $lesson = racedFirstLesson();
    $other = app(ScheduleSession::class)->handle(new NewSession($this->other->id, $this->second->id, $this->quran, $lesson->starts_at, 30, 'Asia/Riyadh'));
    CarbonImmutable::setTestNow('2026-10-06 15:01 UTC');
    app(RecordParticipantJoin::class)->handle($lesson, $this->student->id);
    app(RecordParticipantJoin::class)->handle($other, $this->second->id);

    // A coordinator cancels the first lesson just after the sweeper read the open ones.
    $this->rivalCommitsAfter('select * from "sessions" where "status" in', fn () => $this->rivalCancels($lesson->id));
    CarbonImmutable::setTestNow('2026-10-06 15:10 UTC');

    expect(app(ApplyAttendanceDeadlines::class)->handle())->toBe(1)
        ->and($lesson->refresh()->status)->toBe(SessionStatus::Cancelled)
        ->and($other->refresh()->status)->toBe(SessionStatus::TeacherAbsent);
});

it('makes each weekly lesson once, run twice or by two runs at the same moment', function () {
    $count = Session::query()->count();
    expect(app(MaterializeSessions::class)->handle()['created'])->toBe([])
        ->and(Session::query()->count())->toBe($count);

    // A week on, the horizon reaches two more lessons; another run makes one of them first.
    CarbonImmutable::setTestNow('2026-10-12 07:00 UTC');
    $this->rivalCommitsBefore('insert into "sessions"', function (array $bindings) {
        $key = collect($bindings)->first(fn ($b) => is_string($b) && preg_match('/^[0-9a-f-]{36}:\d{4}-\d{2}-\d{2}$/', $b));
        $slot = DB::table('recurring_slots')->where('id', substr($key, 0, 36))->first();
        $start = CarbonImmutable::parse(substr($key, 37).' '.$slot->local_start, $slot->anchor_tz);
        $this->rivalBooks($slot->teacher_id, $slot->student_id, $this->quran, $start, 30, ['enrollment_id' => $slot->enrollment_id, 'slot_id' => $slot->id, 'occurrence_key' => $key]);
    });

    $result = app(MaterializeSessions::class)->handle();

    expect(count($result['created']))->toBe(1)
        ->and($result['skipped'])->toBe([])
        ->and(Session::query()->count())->toBe($count + 2)
        ->and(Session::query()->whereNotNull('occurrence_key')->count())->toBe(Session::query()->whereNotNull('occurrence_key')->distinct()->count('occurrence_key'));
});

it('never holds minutes for a lesson cancelled while it was being paid for: they go to the next one', function () {
    Event::fake([CreditsGranted::class]);       // fund by hand below, not from the grant
    $account = Enrollment::query()->findOrFail($this->booked->enrollmentId)->credit_account_id;
    app(CreditsApi::class)->grant($account, LotSource::Purchase, null, 30, CarbonImmutable::now()->addDays(42), 'test:one-lesson');
    [$first, $second] = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->take(2)->get()->all();

    // The family cancels the first lesson just after the funding run read the waiting ones.
    $this->rivalCommitsAfter('select * from "sessions" where "enrollment_id" in', fn () => $this->rivalCancels($first->id));

    expect(app(FundSessions::class)->handle($this->booked->enrollmentId))->toBe(1)
        ->and($first->refresh()->status)->toBe(SessionStatus::Cancelled)
        ->and(CreditReservation::query()->where('session_id', $first->id)->exists())->toBeFalse()
        ->and($second->refresh()->funding_status)->toBe(FundingStatus::Funded);
});

it('keeps a lesson paid for just as the unpaid sweeper reached it, and goes on with the rest', function () {
    [$first, $second] = Session::query()->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->take(2)->get()->all();
    CarbonImmutable::setTestNow('2026-10-05 15:30 UTC');      // the first lesson is under 24 hours away and still unpaid

    // The family's payment funds it right after the sweeper's last look at the unpaid ones.
    $this->rivalCommitsAfter('select * from "sessions" where "enrollment_id" is not null', fn () => $this->rival()->update(
        "update sessions set funding_status = 'funded', updated_at = now() where id = ?", [$first->id],
    ), nth: 2);

    expect(app(CancelUnpaidSessions::class)->handle())->toBe(0)
        ->and($first->refresh()->status)->toBe(SessionStatus::Scheduled)
        ->and($second->refresh()->status)->toBe(SessionStatus::Scheduled);
});

it('covers a lesson with the next substitute when the first is booked elsewhere at the last moment', function () {
    $third = User::factory()->withRole(Role::Teacher)->create(['timezone' => 'Africa/Cairo']);
    app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($third->id, 'female', ['ar'], [$this->quran]));
    ShiftSchedule::query()->create(['teacher_id' => $third->id, 'version' => 1, 'timezone' => 'Africa/Cairo', 'effective' => '[2026-09-01,)'])
        ->rules()->create(['teacher_id' => $third->id, 'weekday' => 2, 'local_start' => '17:00', 'duration_min' => 240]);
    $lesson = racedFirstLesson();
    $leave = app(DecideTimeOff::class)->handle(app(RequestTimeOff::class)->handle($this->teacher->id, '2026-10-06', '2026-10-06', null, null, 'sick', null, $this->teacher->id), true, null, $this->admin->id);

    // Whoever is asked first is booked for another lesson at that hour just before they are put on this one.
    $taken = null;
    $this->rivalCommitsBefore('update "session_participants" set "user_id"', function (array $bindings) use ($lesson, &$taken) {
        $taken = $bindings[0];
        $this->rivalBooks($taken, $this->second->id, $this->quran, $lesson->starts_at);
    });

    expect(app(AssignSubstitutes::class)->handle($leave, $this->admin->id))->toBe(['covered' => 1, 'left' => 0])
        ->and($lesson->refresh()->teacher_id)->not->toBe($taken)
        ->and($lesson->teacher_id)->toBeIn([$this->other->id, $third->id])
        ->and($lesson->original_teacher_id)->toBe($this->teacher->id)
        ->and($lesson->events()->where('kind', 'teacher_changed')->sole()->data)->toMatchArray(['from' => $this->teacher->id, 'to' => $lesson->teacher_id])
        ->and(teacherLessonsAt($taken, $lesson->starts_at->addMinute()))->toBe(1);
});
