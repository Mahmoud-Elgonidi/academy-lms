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
        $plan = Plan::query()->where('is_active', true)->find($new->planId);
        $region = PriceRegion::query()->find($new->priceRegionId);
        $price = $plan && $region ? PlanPrice::query()->where('plan_id', $plan->id)->where('price_region_id', $region->id)->first() : null;
        if ($price === null) {
            throw ValidationException::withMessages(['plan' => __('billing::billing.errors.no_price')]);
        }

        return DB::transaction(function () use ($new, $plan, $region, $price) {
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
            ]);

            $this->credits->setLowBalance($new->creditAccountId, $plan->terms()->lowBalanceMinutes());

            return $this->issue->handle($subscription, 1, $new->subjectNameAr, $new->subjectNameEn, $new->actorId);
        });
    }
}
