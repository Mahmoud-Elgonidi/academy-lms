<?php

namespace Modules\Billing\Actions\Discounts;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Discounts\CouponCheck;
use Modules\Billing\Discounts\ReissueBlock;
use Modules\Billing\Models\BillingAccount;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Models\CouponRedemption;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Subscription;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Puts a coupon on a subscription: its next invoices come out with it
 * taken off. An invoice already issued never changes, so the block waiting
 * to be paid takes it only by being issued again: voided (a credit note for
 * a tax invoice) and issued anew with the same blocks (a family invoice
 * with all of them), which needs nothing paid on it, no proof waiting and
 * no online payment under way.
 */
final class ApplyCoupon
{
    public function __construct(
        private readonly CouponCheck $check,
        private readonly ReissueBlock $reissue,
    ) {}

    /** @return array{redemption: CouponRedemption, reissued: ?Invoice} */
    public function handle(string $subscriptionId, string $code, ?string $actorId, bool $reissue = false): array
    {
        return DB::transaction(function () use ($subscriptionId, $code, $actorId, $reissue) {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscriptionId);
            if ($subscription->status === 'cancelled') {
                throw ValidationException::withMessages(['coupon' => __('billing::discounts.errors.cancelled')]);
            }
            if (CouponRedemption::liveFor($subscription->id) !== null) {
                throw ValidationException::withMessages(['coupon' => __('billing::discounts.errors.has_coupon')]);
            }
            $payerId = (string) BillingAccount::query()->whereKey($subscription->billing_account_id)->value('payer_id');
            // The coupon held until this is done, so two payers cannot both take its last use (nor one payer it twice).
            Coupon::query()->where('code', Coupon::normalize($code))->lockForUpdate()->first();
            $coupon = $this->check->usable($code, $payerId, $subscription->currency, $subscription->plan_id);

            $waiting = $reissue ? $this->reissue->waiting($subscription) : null;

            $redemption = CouponRedemption::query()->create([
                'coupon_id' => $coupon->id,
                'billing_account_id' => $subscription->billing_account_id,
                'subscription_id' => $subscription->id,
                'applied_by' => $actorId,
                'applied_at' => CarbonImmutable::now(),
            ]);

            $reissued = null;
            if ($waiting !== null) {
                // The same blocks again: this course's first, and the family's others when it was a family invoice.
                $reissued = $this->reissue->again($subscription, $waiting, __('billing::discounts.reissue_reason', ['code' => $coupon->code]), $actorId);
            }

            app(AuditTrail::class)->record('billing.coupon_applied', 'subscription', $subscription->id, array_filter([
                'code' => $coupon->code,
                'value' => $coupon->valueLabel(),
                'voided' => $waiting?->number,
                'invoice' => $reissued?->number,
            ]));

            return ['redemption' => $redemption->load('coupon'), 'reissued' => $reissued];
        });
    }
}
