<?php

namespace Modules\Scheduling\Actions\Courses;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\BillingApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Scheduling\Domain\Models\CourseStart;
use Modules\Scheduling\Support\CourseDays;
use Modules\Scheduling\Support\StartRefused;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The family calls off a course it started on its own before paying for it
 * (docs/architecture/02, section 27, J3): only its payer, only while it waits
 * for its first payment, and only while no money may be on its way (nothing
 * paid, no transfer receipt waiting, no online payment page open: VoidInvoice's
 * own rules). The course ends today through EndEnrollment (its lessons are
 * cancelled with nothing charged, its weekly times freed for the teacher, its
 * group seat given back, its invoice voided), so the learner may start again.
 * The teacher hears it as any course that ends; audited.
 */
final class CancelOwnStart
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly BillingApi $billing,
        private readonly EndEnrollment $end,
        private readonly CourseDays $days,
    ) {}

    /** Why it cannot be called off now (a reason under scheduling::start.why), or null when it can. */
    public function whyNot(CourseStart $start): ?string
    {
        if ($start->cancelled_at !== null || $this->enrollments->find($start->enrollment_id)?->status !== EnrollmentStatus::PendingPayment) {
            return 'cancel_started';
        }
        $invoice = $this->billing->firstInvoice($start->enrollment_id, $start->payer_id);

        return $invoice === null ? null : match ($this->billing->paymentUnderway($invoice->id)) {
            'paid_some' => 'cancel_paid',
            'proof_waiting' => 'cancel_proof',
            'checkout_open' => 'cancel_checkout',
            default => null,
        };
    }

    /** @return array{lessons: int, invoice: ?string} the lessons cancelled and the voided invoice's number */
    public function handle(string $enrollmentId, string $viewerId): array
    {
        return DB::transaction(function () use ($enrollmentId, $viewerId) {
            $start = CourseStart::query()->where('enrollment_id', $enrollmentId)->lockForUpdate()->first();
            if ($start === null || $start->payer_id !== $viewerId) {
                throw StartRefused::because('cancel_not_yours', status: 404);
            }
            if (($why = $this->whyNot($start)) !== null) {
                throw StartRefused::because($why, status: 409);
            }
            $done = $this->end->handle($enrollmentId, $this->days->today()->toDateString(), (string) __('scheduling::start.cancel_reason'), $viewerId);
            $start->forceFill(['cancelled_at' => CarbonImmutable::now(), 'cancel_reason' => 'family'])->save();
            app(AuditTrail::class)->record('enrollment.own_start_cancelled', 'enrollment', $enrollmentId, array_filter([
                'lessons' => $done['cancelled'], 'invoice' => $done['billing']?->invoiceNumber,
            ], fn ($v) => $v !== null));

            return ['lessons' => (int) $done['cancelled'], 'invoice' => $done['billing']?->invoiceNumber];
        });
    }
}
