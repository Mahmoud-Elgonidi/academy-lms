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

/**
 * Turns a hold into an enrolment, all or nothing (docs/architecture/01, flow
 * "ب"; 04, rule 4): the enrollment and its credit account, the subscription
 * and the first block's invoice, the weekly times on the teacher's clock,
 * and their sessions up to the horizon. Sessions wait unfunded until the
 * invoice is paid; paying grants the minutes and funds them, oldest first.
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
    ) {}

    public function handle(string $batchId, string $studentId, string $subjectId, EnrollmentTerms $terms, string $coordinatorId): EnrollmentBooked
    {
        $holds = $this->batch->handle($batchId, $coordinatorId);
        $first = $holds->first();
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
        if ($plan->sessionMinutes !== $duration || $plan->sessionsPerWeek !== count($times)) {
            throw ValidationException::withMessages(['plan' => __('scheduling::find.errors.plan_mismatch', [
                'minutes' => $duration, 'per_week' => count($times),
            ])]);
        }

        $subject = DB::table('subjects')->where('id', $subjectId)->first(['name_ar', 'name_en']);

        return DB::transaction(function () use ($first, $teacherTz, $times, $studentId, $subjectId, $terms, $coordinatorId, $subject, $batchId) {
            $enrollmentId = $this->enrollments->create(new NewEnrollment($studentId, $subjectId, $first->teacher_id, min(array_column($times, 'from')), $coordinatorId));
            $accountId = $this->credits->openAccount($studentId, 'enrollment', $enrollmentId);
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
            );
        });
    }
}
