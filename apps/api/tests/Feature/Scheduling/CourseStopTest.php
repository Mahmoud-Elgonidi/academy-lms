<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\IssueRenewal;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Subscription;
use Modules\Communication\Models\Notification;
use Modules\Credits\Models\CreditLot;
use Modules\Enrollment\Contracts\DTOs\NewEnrollment;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Enrollment\Models\Enrollment;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Messaging\MessagingRules;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Models\SaasPlan;
use Modules\Scheduling\Actions\BookExtraLesson;
use Modules\Scheduling\Actions\BookMakeup;
use Modules\Scheduling\Actions\BringExistingSchedule;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\Courses\EndEnrollment;
use Modules\Scheduling\Actions\Courses\FollowCourseDays;
use Modules\Scheduling\Actions\Courses\PauseEnrollment;
use Modules\Scheduling\Actions\Courses\ResumeEnrollment;
use Modules\Scheduling\Actions\CreateRecurringSlots;
use Modules\Scheduling\Actions\Dst\PlanDstChanges;
use Modules\Scheduling\Actions\Dst\SendDstNotices;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Actions\ReassignTeacher;
use Modules\Scheduling\Actions\RescheduleSession;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\ShiftNotice;
use Modules\Scheduling\Domain\Slots\WeeklyTime;
use Modules\Scheduling\Queries\AvailabilityQuery;
use Modules\Scheduling\Queries\FamilyNotices;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Plans\PlanLimitReached;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * Pausing and ending a course (docs/architecture/02, section 3, "إيقاف
 * الكورس وإنهاؤه"). The fixture: Monday 5 October 2026, 10:00 in Cairo (the
 * academy's clock); a London student's lessons are Tuesdays and Saturdays
 * 18:00 Cairo with a Cairo teacher, 8 × 30 min a block. Paid, the first eight
 * lessons (6 October to 31 October) are funded.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->id = $this->booked->enrollmentId;
    $this->guardian = User::query()->find(DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id'));
});

afterEach(fn () => CarbonImmutable::setTestNow());

function stopPay(): void
{
    app(RecordPayment::class)->handle(test()->booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, null);
}

/** @return list<string> the Cairo days of the course's lessons with this status */
function stopDays(SessionStatus $status, ?string $reason = null): array
{
    return Session::query()->where('enrollment_id', test()->id)->where('status', $status->value)
        ->when($reason, fn ($q) => $q->whereRaw("decision->>'reason' = ?", [$reason]))
        ->orderBy('starts_at')->pluck('starts_at')
        ->map(fn ($at) => $at->setTimezone('Africa/Cairo')->toDateString())->all();
}

function stopCourse(): Enrollment
{
    return Enrollment::query()->findOrFail(test()->id);
}

function stopSent(User $user, string $template): int
{
    return Notification::query()->where('user_id', $user->id)->where('template_key', $template)->count();
}

function stopAt(string $at): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse($at, 'Africa/Cairo'));
}

it('pauses a course for some days: those lessons cancelled with their minutes back, the credit lasting longer, the weekly times kept', function () {
    stopPay();
    $account = stopCourse()->credit_account_id;
    $expiry = CreditLot::query()->where('account_id', $account)->value('expires_at');
    $slots = RecurringSlot::query()->where('enrollment_id', $this->id)->orderBy('weekday')->get(['status', 'effective'])->toArray();

    $done = app(PauseEnrollment::class)->handle($this->id, '2026-10-13', '2026-10-24', 'Family travel', $this->admin->id);

    $paused = Session::query()->where('enrollment_id', $this->id)->where('status', 'cancelled')->get();
    expect($done)->toBe(['cancelled' => 4, 'minutes' => 120, 'days' => 12])
        ->and(stopDays(SessionStatus::Cancelled, 'enrollment_paused'))->toBe(['2026-10-13', '2026-10-17', '2026-10-20', '2026-10-24'])
        ->and($paused->every(fn (Session $s) => array_intersect_key($s->decision, array_flip(['by', 'reason', 'rule', 'credit', 'teacher_pay_pct'])) == ['by' => 'system', 'reason' => 'enrollment_paused', 'rule' => 22, 'credit' => 'refund', 'teacher_pay_pct' => 0]))->toBeTrue()
        // The pause lies ahead: the course is on until its first day.
        ->and(stopCourse()->status)->toBe(EnrollmentStatus::Active)
        ->and([stopCourse()->paused_from->toDateString(), stopCourse()->paused_until->toDateString(), stopCourse()->pause_reason])->toBe(['2026-10-13', '2026-10-24', 'Family travel'])
        ->and(CreditLot::query()->where('account_id', $account)->value('expires_at')->diffInDays($expiry, true))->toEqual(12)
        // The minutes came back and fund the next lessons waiting, in date order.
        ->and(Session::query()->where('enrollment_id', $this->id)->where('status', 'scheduled')->where('funding_status', FundingStatus::Funded->value)->count())->toBe(8)
        ->and(RecurringSlot::query()->where('enrollment_id', $this->id)->orderBy('weekday')->get(['status', 'effective'])->toArray())->toBe($slots)
        ->and(stopSent($this->guardian, 'scheduling.course_paused_family'))->toBe(1)
        ->and(stopSent($this->student, 'scheduling.course_paused_family'))->toBe(1)
        ->and(stopSent($this->teacher, 'scheduling.course_paused_teacher'))->toBe(1)
        ->and(stopSent($this->guardian, 'scheduling.session_cancelled'))->toBe(0)
        ->and(json_decode(DB::table('audit_logs')->where('action', 'enrollment.paused')->where('subject_id', $this->id)->value('data'), true))
        ->toMatchArray(['from' => '2026-10-13', 'until' => '2026-10-24', 'reason' => 'Family travel', 'lessons' => 4, 'minutes' => 120]);

    // The family reads it above the lessons, never the reason.
    app()->setLocale('en');
    expect(app(FamilyNotices::class)->courses($this->student->id))->toBe(['Quran is paused from Tuesday 13 October until Saturday 24 October.']);
});

it('is paused on its first day, and comes back by itself the day after its last, with its lessons', function () {
    stopPay();
    app(PauseEnrollment::class)->handle($this->id, '2026-10-13', '2026-10-24', 'Exams', $this->admin->id);

    // Every hour, in every academy: the day has begun in Cairo.
    stopAt('2026-10-13 00:30');
    $this->clearTenant();
    $this->artisan('scheduling:courses')->expectsOutputToContain('1 course(s) moved.')->assertSuccessful();
    $this->actAsTenant($this->academy);
    expect(stopCourse()->status)->toBe(EnrollmentStatus::Paused);

    stopAt('2026-10-24 23:30');
    expect(app(FollowCourseDays::class)->handle())->toBe(0)
        ->and(stopCourse()->status)->toBe(EnrollmentStatus::Paused);

    stopAt('2026-10-25 00:30');
    expect(app(FollowCourseDays::class)->handle())->toBe(1)
        ->and(stopCourse()->status)->toBe(EnrollmentStatus::Active)
        ->and(stopCourse()->paused_from)->toBeNull()
        ->and(stopDays(SessionStatus::Scheduled))->toContain('2026-10-27')
        ->and(stopSent($this->guardian, 'scheduling.course_resumed_family'))->toBe(1)
        ->and(stopSent($this->teacher, 'scheduling.course_resumed_teacher'))->toBe(1)
        ->and(json_decode(DB::table('audit_logs')->where('action', 'enrollment.resumed')->value('data'), true))->toMatchArray(['early' => 'on_time', 'until' => '2026-10-24']);
});

it('comes back early when the office resumes it: the paused days still ahead have their lessons again', function () {
    stopPay();
    app(PauseEnrollment::class)->handle($this->id, '2026-10-13', '2026-10-24', 'Travel', $this->admin->id);
    stopAt('2026-10-13 00:30');
    app(FollowCourseDays::class)->handle();

    stopAt('2026-10-15 10:00');
    $made = app(ResumeEnrollment::class)->handle($this->id, $this->admin->id);

    // The three paused days still ahead, and the weeks after the horizon has moved on.
    expect($made)->toBe(6)
        ->and(stopCourse()->status)->toBe(EnrollmentStatus::Active)
        ->and(stopDays(SessionStatus::Scheduled))->toContain('2026-10-17', '2026-10-20', '2026-10-24')
        // Before today stays as it was.
        ->and(stopDays(SessionStatus::Cancelled, 'enrollment_paused'))->toBe(['2026-10-13', '2026-10-17', '2026-10-20', '2026-10-24'])
        ->and(json_decode(DB::table('audit_logs')->where('action', 'enrollment.resumed')->value('data'), true))->toMatchArray(['early' => 'early', 'lessons' => 6])
        ->and(fn () => app(ResumeEnrollment::class)->handle($this->id, $this->admin->id))->toThrow(ValidationException::class);

    // A pause still ahead is called off: nothing was cancelled from today on that needs making.
    app(PauseEnrollment::class)->handle($this->id, '2026-11-03', '2026-11-07', 'Exams', $this->admin->id);
    expect(stopDays(SessionStatus::Cancelled, 'enrollment_paused'))->toContain('2026-11-03', '2026-11-07');
    app(ResumeEnrollment::class)->handle($this->id, $this->admin->id);
    expect(stopDays(SessionStatus::Scheduled))->toContain('2026-11-03', '2026-11-07')
        ->and(stopCourse()->paused_from)->toBeNull();
});

it('makes a pause longer, giving back only the days added, and refuses what a pause cannot be', function () {
    expect(fn () => app(PauseEnrollment::class)->handle($this->id, '2026-10-13', '2026-10-24', 'x', null))
        ->toThrow(ValidationException::class, 'waiting for its first payment');
    stopPay();

    app(PauseEnrollment::class)->handle($this->id, '2026-10-13', '2026-10-24', 'Travel', $this->admin->id);
    $longer = app(PauseEnrollment::class)->handle($this->id, '2026-10-13', '2026-10-31', 'Travel, longer', $this->admin->id);

    expect($longer)->toMatchArray(['cancelled' => 2, 'days' => 7])
        ->and(stopDays(SessionStatus::Cancelled, 'enrollment_paused'))->toBe(['2026-10-13', '2026-10-17', '2026-10-20', '2026-10-24', '2026-10-27', '2026-10-31'])
        ->and(stopSent($this->guardian, 'scheduling.course_paused_family'))->toBe(2)
        ->and(fn () => app(PauseEnrollment::class)->handle($this->id, '2026-10-13', '2026-10-20', 'Shorter', null))->toThrow(ValidationException::class)
        ->and(fn () => app(PauseEnrollment::class)->handle($this->id, '2026-10-14', '2026-11-05', 'Moved', null))->toThrow(ValidationException::class);

    app(ResumeEnrollment::class)->handle($this->id, $this->admin->id);
    expect(fn () => app(PauseEnrollment::class)->handle($this->id, '2026-10-04', '2026-10-10', 'Past', null))->toThrow(ValidationException::class)
        ->and(fn () => app(PauseEnrollment::class)->handle($this->id, '2026-10-12', '2026-10-11', 'Backwards', null))->toThrow(ValidationException::class)
        ->and(fn () => app(PauseEnrollment::class)->handle($this->id, '2026-10-12', '2027-03-01', 'Too long', null))->toThrow(ValidationException::class)
        ->and(fn () => app(PauseEnrollment::class)->handle($this->id, '2026-10-12', '2026-10-13', '  ', null))->toThrow(ValidationException::class)
        ->and(fn () => app(PauseEnrollment::class)->handle($this->id, 'soon', '2026-10-13', 'x', null))->toThrow(ValidationException::class);
});

it('renews nothing while paused, and paying a block issued before the pause adds its minutes without starting the course', function () {
    stopPay();
    $subscription = Subscription::query()->where('enrollment_id', $this->id)->firstOrFail();
    $renewal = app(IssueRenewal::class)->handle($subscription);
    app(PauseEnrollment::class)->handle($this->id, '2026-10-05', '2026-10-20', 'Illness', $this->admin->id);

    expect(stopCourse()->status)->toBe(EnrollmentStatus::Paused)
        ->and(app(IssueRenewal::class)->handle($subscription->refresh()))->toBeNull();

    $renewal->refresh();
    app(RecordPayment::class)->handle($renewal->id, 'cash', $renewal->outstanding(), null, CarbonImmutable::now(), null, null);
    expect(stopCourse()->status)->toBe(EnrollmentStatus::Paused)
        ->and(CreditLot::query()->where('account_id', stopCourse()->credit_account_id)->where('source', 'purchase')->count())->toBe(2)
        ->and($subscription->refresh()->block_no)->toBe(2)
        // Paid during the pause, its minutes last the pause's days longer too, as the pause gave the first block's (42 + 16 days).
        ->and(CreditLot::query()->where('account_id', stopCourse()->credit_account_id)->where('source', 'purchase')->pluck('expires_at')->map->toDateString()->all())
        ->toBe(['2026-12-02', '2026-12-02']);
});

it('needs room in the academy\'s plan to bring a paused course back early, not to come back on its own day', function () {
    stopPay();
    app(PauseEnrollment::class)->handle($this->id, '2026-10-05', '2026-10-20', 'Travel', $this->admin->id);
    // Meanwhile a new student took the plan's one place.
    $plan = SaasPlan::query()->create(['code' => 'one-'.Str::lower(Str::random(4)), 'name_ar' => 'باقة', 'name_en' => 'One student', 'features' => [], 'max_active_students' => 1]);
    app(ChangeAcademyPlan::class)->handle($this->academy->id, $plan->id, 'For this test', [], [], []);
    app(EnrollmentApi::class)->create(new NewEnrollment(User::factory()->withRole(Role::Student)->create()->id, $this->quran, $this->teacher->id, CarbonImmutable::now()));

    expect(fn () => app(ResumeEnrollment::class)->handle($this->id, $this->admin->id))->toThrow(PlanLimitReached::class)
        ->and(stopCourse()->status)->toBe(EnrollmentStatus::Paused);

    // The day after its pause it comes back as planned.
    stopAt('2026-10-21 00:30');
    expect(app(FollowCourseDays::class)->handle())->toBe(1)
        ->and(stopCourse()->status)->toBe(EnrollmentStatus::Active);
});

it('makes no lessons on a pause\'s days when it makes the coming weeks', function () {
    stopPay();
    app(PauseEnrollment::class)->handle($this->id, '2026-12-01', '2026-12-08', 'Winter', $this->admin->id);

    stopAt('2026-11-20 10:00');
    app(MaterializeSessions::class)->handle([$this->id]);

    expect(stopDays(SessionStatus::Scheduled))->not->toContain('2026-12-01', '2026-12-05', '2026-12-08')->toContain('2026-11-28', '2026-12-12');
});

it('ends a course today: its lessons cancelled with their minutes back, its weekly times free, its waiting block voided', function () {
    // Never paid: the first block's invoice waits with nothing on it.
    $done = app(EndEnrollment::class)->handle($this->id, '2026-10-05', 'Moved abroad', $this->admin->id);

    expect($done['billing']->outcome)->toBe('voided')
        ->and(Invoice::query()->find($this->booked->invoice->id)->status)->toBe('void')
        ->and(stopCourse()->status)->toBe(EnrollmentStatus::Ended)
        ->and(stopCourse()->ended_at)->not->toBeNull()
        ->and(stopDays(SessionStatus::Scheduled))->toBe([])
        ->and(Session::query()->where('enrollment_id', $this->id)->where('status', 'cancelled')->get()->every(fn (Session $s) => $s->decision['reason'] === 'enrollment_ended' && $s->decision['rule'] === 22))->toBeTrue()
        ->and(RecurringSlot::query()->where('enrollment_id', $this->id)->pluck('status')->unique()->all())->toBe(['ended'])
        ->and(stopSent($this->guardian, 'scheduling.course_ended_family'))->toBe(1)
        ->and(stopSent($this->teacher, 'scheduling.course_ended_teacher'))->toBe(1)
        ->and(stopSent($this->guardian, 'scheduling.session_cancelled'))->toBe(0)
        ->and(json_decode(DB::table('audit_logs')->where('action', 'enrollment.ended')->value('data'), true))->toMatchArray(['on' => '2026-10-05', 'reason' => 'Moved abroad', 'invoice_outcome' => 'voided']);

    // The teacher's Tuesday 18:00 is free again: another student may take it every week.
    $tuesday = [CarbonImmutable::parse('2026-10-13 18:00', 'Africa/Cairo')->utc(), CarbonImmutable::parse('2026-10-13 18:30', 'Africa/Cairo')->utc()];
    $other = User::factory()->withRole(Role::Student)->create();
    expect(app(AvailabilityQuery::class)->forTeachers([$this->teacher->id], ...$tuesday)[$this->teacher->id]->free->contains($tuesday[0]->getTimestamp(), $tuesday[1]->getTimestamp()))->toBeTrue()
        ->and(app(CreateRecurringSlots::class)->handle($this->id, $this->teacher->id, $other->id, 'Africa/Cairo', [['time' => WeeklyTime::at(2, '18:00', 30), 'from' => CarbonImmutable::parse('2026-10-13')]], null))->toHaveCount(1);

    // Ended for good: no pause, no resume, no booking, no new teacher; a new enrollment is the way back.
    expect(fn () => app(EndEnrollment::class)->handle($this->id, '2026-10-06', 'Again', null))->toThrow(ValidationException::class)
        ->and(fn () => app(PauseEnrollment::class)->handle($this->id, '2026-10-06', '2026-10-07', 'x', null))->toThrow(ValidationException::class)
        ->and(fn () => app(ResumeEnrollment::class)->handle($this->id, null))->toThrow(ValidationException::class)
        ->and(fn () => app(BookMakeup::class)->handle($this->id, CarbonImmutable::parse('2026-10-17 19:00', 'Africa/Cairo'), null))->toThrow(ValidationException::class)
        ->and(fn () => app(ReassignTeacher::class)->handle($this->id, $this->other->id, CarbonImmutable::parse('2026-10-10'), null))->toThrow(ValidationException::class);
});

it('ends a course from a later day: lessons kept until then, none renewed, the rest done on that day', function () {
    stopPay();
    $done = app(EndEnrollment::class)->handle($this->id, '2026-10-20', 'Finished the programme', $this->admin->id);

    expect($done)->toMatchArray(['cancelled' => 12, 'minutes' => 120, 'billing' => null])
        ->and(stopCourse()->status)->toBe(EnrollmentStatus::Active)
        ->and(stopCourse()->ends_on->toDateString())->toBe('2026-10-20')
        ->and(stopDays(SessionStatus::Scheduled))->toBe(['2026-10-06', '2026-10-10', '2026-10-13', '2026-10-17'])
        // Live until then, so the coming weeks still make the lessons before the end, but none after.
        ->and(RecurringSlot::query()->where('enrollment_id', $this->id)->orderBy('weekday')->pluck('effective')->all())->toBe(['[2026-10-06,2026-10-20)', '[2026-10-10,2026-10-20)'])
        ->and(RecurringSlot::query()->where('enrollment_id', $this->id)->pluck('status')->unique()->all())->toBe(['active'])
        ->and(Subscription::query()->taught()->count())->toBe(0)
        ->and(app(FamilyNotices::class)->courses($this->student->id))->toHaveCount(1);

    // A renewal would be due: the course is ending, so none goes out.
    $subscription = Subscription::query()->where('enrollment_id', $this->id)->firstOrFail();
    expect(app(IssueRenewal::class)->handle($subscription))->toBeNull()
        ->and(fn () => app(BookExtraLesson::class)->handle($this->id, CarbonImmutable::parse('2026-10-24 19:00', 'Africa/Cairo'), null))->toThrow(ValidationException::class, 'ended by then')
        ->and(fn () => app(EndEnrollment::class)->handle($this->id, '2026-10-12', 'Sooner', null))->toThrow(ValidationException::class);

    stopAt('2026-10-20 00:30');
    expect(app(FollowCourseDays::class)->handle())->toBe(1)
        ->and(stopCourse()->status)->toBe(EnrollmentStatus::Ended)
        ->and(RecurringSlot::query()->where('enrollment_id', $this->id)->pluck('status')->unique()->all())->toBe(['ended'])
        ->and(app(FollowCourseDays::class)->handle())->toBe(0);
});

it('ends a paused course: the pause gives way to the end', function () {
    stopPay();
    app(PauseEnrollment::class)->handle($this->id, '2026-10-05', '2026-10-24', 'Travel', $this->admin->id);
    stopAt('2026-10-08 10:00');

    $done = app(EndEnrollment::class)->handle($this->id, '2026-10-17', 'Not coming back', $this->admin->id);
    expect(stopCourse()->status)->toBe(EnrollmentStatus::Paused)
        ->and(stopDays(SessionStatus::Cancelled, 'enrollment_ended'))->toContain('2026-10-27')->not->toContain('2026-10-17')
        ->and($done['cancelled'])->toBeGreaterThan(0);

    stopAt('2026-10-17 00:30');
    app(FollowCourseDays::class)->handle();
    expect(stopCourse()->status)->toBe(EnrollmentStatus::Ended)
        ->and(stopCourse()->paused_from)->toBeNull()
        ->and(stopDays(SessionStatus::Scheduled))->toBe([]);
});

it('books nothing on a paused day or after the end, and moves no lesson there', function () {
    stopPay();
    // A makeup is owed: the teacher cancelled a lesson.
    $first = Session::query()->where('enrollment_id', $this->id)->orderBy('starts_at')->firstOrFail();
    app(CancelSession::class)->handle($first, 'teacher', 'illness', null, null);
    app(PauseEnrollment::class)->handle($this->id, '2026-10-13', '2026-10-24', 'Travel', $this->admin->id);
    $saturday = Session::query()->where('enrollment_id', $this->id)->where('status', 'scheduled')->orderBy('starts_at')->firstOrFail();   // 10 October

    expect(fn () => app(BookMakeup::class)->handle($this->id, CarbonImmutable::parse('2026-10-17 19:00', 'Africa/Cairo'), null))->toThrow(ValidationException::class, 'paused then')
        ->and(fn () => app(RescheduleSession::class)->handle($saturday, CarbonImmutable::parse('2026-10-17 19:00', 'Africa/Cairo'), 'academy', null))->toThrow(ValidationException::class, 'paused then')
        ->and(app(BookMakeup::class)->handle($this->id, CarbonImmutable::parse('2026-10-27 19:00', 'Africa/Cairo'), null)->type->value)->toBe('makeup');
});

it('tells no family of a clock change on a paused course\'s days or after it ends', function () {
    // London goes back an hour on 25 October, Cairo on 30 October: the student's hour moves twice.
    stopPay();
    app(PlanDstChanges::class)->handle();
    expect(ShiftNotice::query()->where('enrollment_id', $this->id)->count())->toBeGreaterThan(0);

    app(PauseEnrollment::class)->handle($this->id, '2026-10-20', '2026-11-03', 'Travel', $this->admin->id);
    expect(ShiftNotice::query()->where('enrollment_id', $this->id)->count())->toBe(0);

    // Paused already: whatever notice there is waits.
    app(ResumeEnrollment::class)->handle($this->id, $this->admin->id);
    app(PlanDstChanges::class)->handle();
    expect(ShiftNotice::query()->where('enrollment_id', $this->id)->count())->toBeGreaterThan(0);
    Enrollment::query()->whereKey($this->id)->update(['status' => 'paused']);
    expect(app(SendDstNotices::class)->handle(CarbonImmutable::parse('2026-10-20 10:00', 'Africa/Cairo')))->toBe(0);

    Enrollment::query()->whereKey($this->id)->update(['status' => 'active']);
    app(EndEnrollment::class)->handle($this->id, '2026-10-05', 'Done', $this->admin->id);
    expect(ShiftNotice::query()->where('enrollment_id', $this->id)->count())->toBe(0);
});

it('keeps a paused course\'s conversation and schedule, and lets an ended one\'s go', function () {
    stopPay();
    $rules = app(MessagingRules::class);
    $existing = fn () => app(BringExistingSchedule::class)->exists($this->student->id, $this->teacher->id, $this->quran);

    app(PauseEnrollment::class)->handle($this->id, '2026-10-05', '2026-10-24', 'Travel', $this->admin->id);
    expect($rules->teaches($this->teacher->id, $this->student->id))->toBeTrue()
        ->and($existing())->toBeTrue();

    app(EndEnrollment::class)->handle($this->id, '2026-10-05', 'Done', $this->admin->id);
    expect($rules->teaches($this->teacher->id, $this->student->id))->toBeFalse()
        ->and($existing())->toBeFalse();
});
