<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Discounts\InvoiceDiscounts;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Subscription;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Extra lessons on a course (docs/architecture/02, section 3, "ترقية
 * الباقة"): an invoice for N lessons at the price of one lesson of its
 * block, less the family discount (a coupon is for blocks only). Paying it
 * adds their minutes to the course's balance, valid as long as a block;
 * waiting lessons are funded from them first, then a lesson can be booked.
 * One such invoice waits at a time per course.
 */
final class BuyExtraLessons
{
    public const MAX = 10;

    public function __construct(
        private readonly WriteInvoice $write,
        private readonly InvoiceDiscounts $discounts,
        private readonly EnrollmentApi $enrollments,
    ) {}

    public function handle(string $subscriptionId, int $lessons, ?string $actorId, string $channel = 'office'): Invoice
    {
        if ($lessons < 1 || $lessons > self::MAX) {
            throw ValidationException::withMessages(['lessons' => __('billing::discounts.errors.extra_count', ['max' => self::MAX])]);
        }

        return DB::transaction(function () use ($subscriptionId, $lessons, $actorId, $channel) {
            $subscription = Subscription::query()->with('plan')->lockForUpdate()->findOrFail($subscriptionId);
            $enrollment = $this->enrollments->find($subscription->enrollment_id);
            if ($subscription->status === 'cancelled' || $enrollment === null || $enrollment->status->value !== 'active') {
                throw ValidationException::withMessages(['lessons' => __('billing::discounts.errors.extra_inactive')]);
            }
            $waiting = Invoice::query()->where('subscription_id', $subscription->id)->where('status', 'issued')
                ->whereHas('lines', fn ($q) => $q->where('kind', 'extra'))->value('number');
            if ($waiting !== null) {
                throw ValidationException::withMessages(['lessons' => __('billing::discounts.errors.extra_waiting', ['number' => $waiting])]);
            }

            $plan = $subscription->plan;
            $unit = $subscription->lessonPrice()->minor;
            [$ar, $en] = $subscription->subjectNames();
            $describe = fn (string $locale, string $subject) => __('billing::billing.lines.extra', ['subject' => $subject, 'minutes' => $plan->session_minutes], $locale);
            $line = [
                'kind' => 'extra',
                'description_ar' => $describe('ar', $ar),
                'description_en' => $describe('en', $en),
                'quantity' => $lessons,
                'unit_minor' => $unit,
                'amount_minor' => $unit * $lessons,
                'meta' => [
                    'minutes' => $plan->session_minutes * $lessons,
                    'sessions' => $lessons,
                    'session_minutes' => $plan->session_minutes,
                    'validity_days' => $plan->validity_days,
                    'channel' => $channel,
                ],
            ];

            $invoice = $this->write->handle($subscription, [$line, ...$this->discounts->lines($subscription, $unit * $lessons, withCoupon: false)], $actorId);
            app(AuditTrail::class)->record('billing.extra_lessons', 'invoice', $invoice->id, [
                'number' => $invoice->number, 'lessons' => $lessons, 'total' => $invoice->total()->format(), 'channel' => $channel,
            ]);

            return $invoice;
        });
    }
}
