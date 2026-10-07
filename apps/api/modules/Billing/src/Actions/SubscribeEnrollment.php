<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Contracts\DTOs\NewSubscription;
use Modules\Billing\Models\BillingAccount;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Models\PriceRegion;
use Modules\Billing\Models\Subscription;
use Modules\Credits\Contracts\CreditsApi;

/**
 * Puts an enrollment on a plan at the payer's regional price, sets the
 * credit account's renewal mark (N sessions left) and issues block 1.
 */
final class SubscribeEnrollment
{
    public function __construct(
        private readonly IssueBlockInvoice $issue,
        private readonly CreditsApi $credits,
    ) {}

    public function handle(NewSubscription $new): Invoice
    {
        return DB::transaction(function () use ($new) {
            [$subscription] = $this->open($new, blocksPaid: 0);

            return $this->issue->handle($subscription, 1, $new->subjectNameAr, $new->subjectNameEn, $new->actorId);
        });
    }

    /**
     * An enrollment brought over from before the academy used the platform:
     * the block under way was paid there, so no invoice for it; its minutes
     * left come in as the opening balance. The next block's invoice goes out
     * as usual when the balance reaches the mark, at once if it already has.
     */
    public function adopt(NewSubscription $new): Subscription
    {
        return DB::transaction(function () use ($new) {
            [$subscription, $lowMark] = $this->open($new, blocksPaid: 1);
            if ($this->credits->balance($new->creditAccountId)->remainingMin() <= $lowMark) {
                $this->issue->handle($subscription, 2, $new->subjectNameAr, $new->subjectNameEn, $new->actorId);
            }

            return $subscription;
        });
    }

    /** @return array{0: Subscription, 1: int} the subscription and its renewal mark in minutes */
    private function open(NewSubscription $new, int $blocksPaid): array
    {
        $plan = Plan::query()->where('is_active', true)->find($new->planId);
        $region = PriceRegion::query()->find($new->priceRegionId);
        $price = $plan && $region ? PlanPrice::query()->where('plan_id', $plan->id)->where('price_region_id', $region->id)->first() : null;
        if ($price === null) {
            throw ValidationException::withMessages(['plan' => __('billing::billing.errors.no_price')]);
        }

        $account = BillingAccount::query()->firstOrCreate(
            ['payer_id' => $new->payerId, 'currency' => $price->currency],
            ['price_region_id' => $region->id],
        );
        DB::table('billing_account_students')->insertOrIgnore([
            'academy_id' => $account->academy_id, 'billing_account_id' => $account->id, 'student_id' => $new->studentId,
        ]);

        $subscription = Subscription::query()->create([
            'billing_account_id' => $account->id,
            'enrollment_id' => $new->enrollmentId,
            'plan_id' => $plan->id,
            'credit_account_id' => $new->creditAccountId,
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency,
            'block_no' => $blocksPaid,
        ]);

        $lowMark = $plan->terms()->lowBalanceMinutes();
        $this->credits->setLowBalance($new->creditAccountId, $lowMark);

        return [$subscription, $lowMark];
    }
}
