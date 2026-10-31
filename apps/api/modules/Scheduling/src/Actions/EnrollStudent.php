<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\NewSubscription;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Enrollment\Contracts\DTOs\NewEnrollment;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Scheduling\Contracts\DTOs\EnrollmentBooked;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Domain\Models\BookingHold;
use Modules\Scheduling\Domain\Slots\WeeklyTime;
use Modules\Scheduling\Support\MinorsGuard;

/**
 * Turns a hold into an enrolment, all or nothing (docs/architecture/01, flow
 * "ب"; 04, rule 4): the enrollment and its credit account, the subscription
 * and the first block's invoice, the weekly times on the teacher's clock,
 * and their sessions up to the horizon. Sessions wait unfunded until the
 * invoice is paid; paying grants the minutes and funds them, oldest first.
 * On a shared package ("12 lessons in any subject") the student already
 * has with this payer, the course joins it instead: its lessons draw on the
 * package's balance, with no invoice of its own, and it starts at once when
 * the package is paid. A shared plan's weekly lessons are across courses,
 * so a course on one only has to match its lesson length.
 */
final class EnrollStudent
{
    public function __construct(
        private readonly TakeHeldBatch $batch,
        private readonly EnrollmentApi $enrollments,
        private readonly CreditsApi $credits,
        private readonly BillingApi $billing,
        private readonly CreateRecurringSlots $slots,
        private readonly MaterializeSessions $materialize,
        private readonly FundSessions $fund,
        private readonly MinorsGuard $minors,
    ) {}

    public function handle(string $batchId, string $studentId, string $subjectId, EnrollmentTerms $terms, string $coordinatorId): EnrollmentBooked
    {
        $holds = $this->batch->handle($batchId, $coordinatorId);
        $first = $holds->first();
        $this->minors->assert($first->teacher_id, $studentId);
        $teacherTz = DB::table('users')->where('id', $first->teacher_id)->value('timezone') ?? 'UTC';
        $duration = (int) $first->starts_at->diffInMinutes($first->ends_at);

        $times = [];
        foreach ($holds as $hold) {
            /** @var BookingHold $hold */
            $local = $hold->starts_at->setTimezone($teacherTz);
            $times[$local->dayOfWeekIso.'|'.$local->format('H:i')] ??= ['time' => WeeklyTime::at($local->dayOfWeekIso, $local->format('H:i'), $duration), 'from' => $local->startOfDay()];
        }

        $plan = collect($this->billing->planOptions())->first(fn (PlanOption $p) => $p->id === $terms->planId);
        if ($plan === null) {
            throw ValidationException::withMessages(['plan' => __('scheduling::find.errors.plan')]);
        }
        if ($plan->group) {
            // Private lessons take a private plan; a group plan is for a seat in a group (JoinGroup).
            throw ValidationException::withMessages(['plan' => __('scheduling::groups.errors.plan_is_group')]);
        }
        if ($plan->sessionMinutes !== $duration || (! $plan->shared && $plan->sessionsPerWeek !== count($times))) {
            throw ValidationException::withMessages(['plan' => __('scheduling::find.errors.plan_mismatch', [
                'minutes' => $duration, 'per_week' => count($times),
            ])]);
        }

        $subject = DB::table('subjects')->where('id', $subjectId)->first(['name_ar', 'name_en']);
        $package = $plan->shared ? $this->billing->sharedPackage($studentId, $plan->id, $terms->payerId) : null;
        if ($package !== null && filled($terms->couponCode)) {
            throw ValidationException::withMessages(['coupon' => __('scheduling::find.errors.coupon_on_package')]);
        }

        return DB::transaction(function () use ($first, $teacherTz, $times, $studentId, $subjectId, $terms, $coordinatorId, $subject, $batchId, $plan, $package) {
            $enrollmentId = $this->enrollments->create(new NewEnrollment($studentId, $subjectId, $first->teacher_id, min(array_column($times, 'from')), $coordinatorId));

            $invoice = null;
            if ($package !== null) {
                $this->enrollments->attachCreditAccount($enrollmentId, $package->creditAccountId);
                if ($package->paid) {
                    $this->enrollments->activate($enrollmentId, $coordinatorId);
                }
            } else {
                $accountId = $this->credits->openAccount($studentId, $plan->shared ? 'student_pool' : 'enrollment', $enrollmentId);
                $this->enrollments->attachCreditAccount($enrollmentId, $accountId);

                $invoice = $this->billing->subscribe(new NewSubscription(
                    enrollmentId: $enrollmentId,
                    studentId: $studentId,
                    creditAccountId: $accountId,
                    planId: $terms->planId,
                    payerId: $terms->payerId,
                    priceRegionId: $terms->priceRegionId,
                    subjectNameAr: $subject->name_ar ?? '',
                    subjectNameEn: $subject->name_en ?? '',
                    actorId: $coordinatorId,
                    couponCode: $terms->couponCode,
                ));
            }

            $this->slots->handle($enrollmentId, $first->teacher_id, $studentId, $teacherTz, $times, $coordinatorId);
            BookingHold::query()->where('batch_id', $batchId)->delete();
            $result = $this->materialize->handle([$enrollmentId], actorId: $coordinatorId);
            $this->fund->handle($enrollmentId);

            $starts = array_map(fn ($s) => $s->starts_at, $result['created']);

            return new EnrollmentBooked(
                enrollmentId: $enrollmentId,
                invoice: $invoice,
                sessions: count($result['created']),
                firstSession: $starts === [] ? null : min($starts),
                skippedDates: array_column($result['skipped'], 'date'),
                joinedPackage: $package !== null,
            );
        });
    }
}
