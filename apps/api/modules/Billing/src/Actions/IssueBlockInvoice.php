<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Discounts\InvoiceDiscounts;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Subscription;

/**
 * Issues the invoice for one block of a subscription: the plan's sessions
 * at the agreed price, less the family discount or the subscription's
 * coupon, VAT per the academy's settings, the next number. One invoice per
 * block: asking again returns the one already issued.
 */
final class IssueBlockInvoice
{
    public function __construct(private readonly WriteInvoice $write, private readonly InvoiceDiscounts $discounts) {}

    public function handle(Subscription $subscription, int $blockNo, string $subjectAr, string $subjectEn, ?string $actorId = null): Invoice
    {
        return DB::transaction(function () use ($subscription, $blockNo, $subjectAr, $subjectEn, $actorId) {
            Subscription::query()->whereKey($subscription->id)->lockForUpdate()->first();

            $existing = Invoice::query()
                ->where('subscription_id', $subscription->id)
                ->whereIn('status', ['issued', 'paid'])
                ->whereHas('lines', fn ($q) => $q->where('kind', 'block')->whereRaw("(meta->>'block_no')::int = ?", [$blockNo]))
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $plan = $subscription->plan;
            $block = [
                'kind' => 'block',
                'description_ar' => __('billing::billing.lines.block', ['subject' => $subjectAr, 'plan' => $plan->name_ar, 'block' => $blockNo], 'ar'),
                'description_en' => __('billing::billing.lines.block', ['subject' => $subjectEn, 'plan' => $plan->name_en, 'block' => $blockNo], 'en'),
                'quantity' => 1,
                'unit_minor' => $subscription->amount_minor,
                'amount_minor' => $subscription->amount_minor,
                'meta' => [
                    'block_no' => $blockNo,
                    'minutes' => $plan->terms()->minutes(),
                    'sessions' => $plan->sessions_per_block,
                    'session_minutes' => $plan->session_minutes,
                    'validity_days' => $plan->validity_days,
                ],
            ];

            return $this->write->handle($subscription, [$block, ...$this->discounts->lines($subscription, $subscription->amount_minor, withCoupon: true)], $actorId);
        });
    }
}
