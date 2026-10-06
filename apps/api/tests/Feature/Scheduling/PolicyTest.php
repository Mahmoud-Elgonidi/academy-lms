<?php

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\RecordPayment;
use Modules\Credits\Models\CreditAccount;
use Modules\Credits\Models\CreditLot;
use Modules\Credits\Models\CreditReservation;
use Modules\Enrollment\Models\Enrollment;
use Modules\Scheduling\Actions\AllowUnfundedSession;
use Modules\Scheduling\Actions\BookMakeup;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\CancelUnpaidSessions;
use Modules\Scheduling\Actions\Policy\SavePolicySettings;
use Modules\Scheduling\Actions\ReassignTeacher;
use Modules\Scheduling\Actions\RescheduleSession;
use Modules\Scheduling\Contracts\DTOs\EnrollmentBooked;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Enums\SessionType;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\SharedKernel\Money\Money;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

beforeEach(function () {
    // Monday 5 October 2026, 10:00 in Cairo. The first lesson is Tuesday 18:00 Cairo, 32 hours away.
    $this->enrolStudentOnPlan();
});

afterEach(fn () => CarbonImmutable::setTestNow());

function payBlock(EnrollmentBooked $booked): void
{
    app(RecordPayment::class)->handle($booked->invoice->id, 'cash', Money::parse('400', 'SAR'), null, CarbonImmutable::now(), null, null);
}

/** @return list<Session> */
function lessons(): array
{
    return Session::query()->where('enrollment_id', test()->booked->enrollmentId)->where('type', 'regular')->orderBy('starts_at')->get()->all();
}

function account(): CreditAccount
{
    return CreditAccount::query()->findOrFail(Enrollment::query()->findOrFail(test()->booked->enrollmentId)->credit_account_id);
}

it('gives the credit back for a cancellation in time, records the policy version, and funds the next lesson', function () {
    payBlock($this->booked);
    [$first] = lessons();

    app(CancelSession::class)->handle($first, 'student', 'illness', null, $this->admin->id);

    $first->refresh();
    expect($first->status)->toBe(SessionStatus::Cancelled)
        ->and($first->decision)->toMatchArray(['by' => 'student', 'rule' => 2, 'credit' => 'refund', 'policy_version' => 1])
        ->and($first->policy_version_id)->toBe(PolicyVersion::current()->id)
        ->and(lessons()[8]->funding_status)->toBe(FundingStatus::Funded);
});

it('counts cancellations per block: beyond the quota the lesson is lost, unless a manager waives it', function () {
    payBlock($this->booked);
    $l = lessons();

    app(CancelSession::class)->handle($l[0], 'student', 'travel', null, null);
    app(CancelSession::class)->handle($l[1], 'student', 'travel', null, null);
    $third = app(CancelSession::class)->preview($l[2], 'student', 'travel');
    app(CancelSession::class)->handle($l[2], 'student', 'travel', null, null);
    app(CancelSession::class)->handle($l[3], 'student', 'travel', null, $this->admin->id, waive: true);

    expect([$third->rule, $third->credit, $third->kpi])->toBe([3, 'forfeit', 'late_student_cancel'])
        ->and(CreditReservation::query()->where('session_id', $l[2]->id)->value('status'))->toBe('consumed')
        ->and($l[3]->refresh()->decision)->toMatchArray(['credit' => 'refund', 'waived' => true])
        ->and(CreditReservation::query()->where('session_id', $l[3]->id)->value('status'))->toBe('released');
});

it('charges a late cancellation and pays the teacher half (rule 4)', function () {
    payBlock($this->booked);
    [$first] = lessons();
    CarbonImmutable::setTestNow($first->starts_at->subHours(3));

    app(CancelSession::class)->handle($first, 'student', 'family', null, null);

    expect($first->refresh()->decision)->toMatchArray(['rule' => 4, 'credit' => 'forfeit', 'teacher_pay_pct' => 50])
        ->and(account()->consumed_min)->toBe(30);
});

it('owes a makeup when the teacher cancels, valid as long as the academy says', function () {
    payBlock($this->booked);
    app(SavePolicySettings::class)->handle(['makeup_validity_days' => 14], $this->admin->id);
    [$first] = lessons();

    app(CancelSession::class)->handle($first, 'teacher', 'illness', null, null);

    $lot = CreditLot::query()->where('source', 'makeup')->firstOrFail();
    // The first save is version 1: the defaults were never saved before.
    expect($first->refresh()->decision)->toMatchArray(['rule' => 7, 'credit' => 'makeup', 'policy_version' => 1])
        ->and($lot->expires_at->toDateString())->toBe('2026-10-19');
});

it('books a makeup from makeup credit only, and a cancelled makeup is not made up again', function () {
    payBlock($this->booked);
    [$first] = lessons();

    expect(fn () => app(BookMakeup::class)->handle($this->booked->enrollmentId, CarbonImmutable::parse('2026-10-06 19:00', 'Africa/Cairo'), null))
        ->toThrow(ValidationException::class);

    app(CancelSession::class)->handle($first, 'teacher', 'illness', null, null);
    $makeup = app(BookMakeup::class)->handle($this->booked->enrollmentId, CarbonImmutable::parse('2026-10-06 19:00', 'Africa/Cairo'), $this->admin->id, $first->id);

    expect($makeup->type)->toBe(SessionType::Makeup)
        ->and($makeup->funding_status)->toBe(FundingStatus::Makeup)
        ->and($makeup->makeup_for_session_id)->toBe($first->id)
        ->and(CreditLot::query()->where('source', 'makeup')->value('remaining_min'))->toBe(0);

    app(CancelSession::class)->handle($makeup, 'student', 'illness', null, null);
    expect($makeup->refresh()->decision)->toMatchArray(['rule' => 17, 'credit' => 'forfeit'])
        ->and(CreditLot::query()->where('source', 'makeup')->count())->toBe(1);
});

it('moves a lesson for the student with notice and within the quota, keeping its credit', function () {
    payBlock($this->booked);
    [$first] = lessons();
    $reservation = CreditReservation::query()->where('session_id', $first->id)->value('status');

    app(RescheduleSession::class)->handle($first, CarbonImmutable::parse('2026-10-06 19:30', 'Africa/Cairo'), 'student', $this->admin->id);

    $first->refresh()->load('participants');
    expect($first->starts_at->setTimezone('Africa/Cairo')->format('H:i'))->toBe('19:30')
        ->and([$first->reschedule_count, $first->is_detached])->toBe([1, true])
        ->and($first->decision)->toMatchArray(['rule' => 1, 'credit' => 'keep'])
        ->and($reservation)->toBe('held')
        ->and(CreditReservation::query()->where('session_id', $first->id)->value('status'))->toBe('held')
        ->and($first->participants->every(fn ($p) => str_contains($p->getRawOriginal('block_period'), '16:30:00')))->toBeTrue();
});

it('refuses a late move for the student, lets the academy do it, and never outside the teacher\'s shift', function () {
    [$first] = lessons();
    CarbonImmutable::setTestNow($first->starts_at->subHours(2));
    $later = CarbonImmutable::parse('2026-10-06 20:00', 'Africa/Cairo');

    expect(fn () => app(RescheduleSession::class)->handle($first, $later, 'student', null))->toThrow(ValidationException::class);
    app(RescheduleSession::class)->handle($first, $later, 'academy', $this->admin->id);
    expect($first->refresh()->decision['rule'])->toBe(20)
        ->and(fn () => app(RescheduleSession::class)->handle($first->refresh(), CarbonImmutable::parse('2026-10-06 23:00', 'Africa/Cairo'), 'academy', null))
        ->toThrow(ValidationException::class);
});

it('cancels unpaid enrolled lessons a day ahead, unless a manager let one go ahead', function () {
    $l = lessons();
    app(AllowUnfundedSession::class)->handle($l[1], $this->admin->id);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-09 18:00', 'Africa/Cairo'));   // Friday: Saturday's lesson is 24 hours away

    $cancelled = app(CancelUnpaidSessions::class)->handle();

    expect($cancelled)->toBe(0)                       // Tuesday's lesson is past; Saturday's was allowed
        ->and($l[1]->refresh()->status)->toBe(SessionStatus::Scheduled);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-12 19:00', 'Africa/Cairo'));
    expect(app(CancelUnpaidSessions::class)->handle())->toBe(1)
        ->and($l[2]->refresh()->decision)->toMatchArray(['by' => 'system', 'rule' => 16]);
});

it('leaves unpaid lessons alone when the academy turns automatic cancellation off', function () {
    app(SavePolicySettings::class)->handle(['unpaid_cancel_hours' => 0], $this->admin->id);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 20:00', 'Africa/Cairo'));

    expect(app(CancelUnpaidSessions::class)->handle())->toBe(0);
});

it('moves the student to another teacher from a date, with the same times and the same credit', function () {
    payBlock($this->booked);
    $l = lessons();

    $result = app(ReassignTeacher::class)->handle($this->booked->enrollmentId, $this->other->id, CarbonImmutable::parse('2026-10-13'), $this->admin->id);

    // Cairo and Riyadh share a clock until Egypt leaves summer time on 29 October; after that
    // the lessons follow the new teacher's clock: 18:00 Riyadh, an hour earlier than 18:00 Cairo.
    $after = collect(lessons());
    expect($result)->toBe(['moved' => 14, 'retimed' => 9])   // seven Tuesdays and seven Saturdays from 13 October
        ->and($after->last()->starts_at->setTimezone('Asia/Riyadh')->format('H:i'))->toBe('18:00')
        ->and($l[0]->refresh()->teacher_id)->toBe($this->teacher->id)
        ->and($after->where('starts_at', '>=', CarbonImmutable::parse('2026-10-13'))->every(fn (Session $s) => $s->teacher_id === $this->other->id))->toBeTrue()
        ->and($after->count())->toBe(16)
        ->and($l[5]->refresh()->funding_status)->toBe(FundingStatus::Funded)
        ->and(RecurringSlot::query()->where('status', 'active')->pluck('teacher_id')->unique()->all())->toBe([$this->other->id])
        ->and(RecurringSlot::query()->where('status', 'active')->pluck('local_start')->map(fn ($t) => substr($t, 0, 5))->unique()->all())->toBe(['18:00'])
        ->and(Enrollment::query()->findOrFail($this->booked->enrollmentId)->teacher_id)->toBe($this->other->id);
});

it('refuses to move the student to a teacher who is not free', function () {
    // The other teacher only works Saturdays.
    ShiftSchedule::query()->where('teacher_id', $this->other->id)->firstOrFail()->rules()->where('weekday', 2)->delete();

    expect(fn () => app(ReassignTeacher::class)->handle($this->booked->enrollmentId, $this->other->id, CarbonImmutable::parse('2026-10-13'), null))
        ->toThrow(ValidationException::class)
        ->and(Enrollment::query()->findOrFail($this->booked->enrollmentId)->teacher_id)->toBe($this->teacher->id);
});
