<?php

namespace Modules\Billing\Actions\Discounts;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Discounts\ReissueBlock;
use Modules\Billing\Models\CouponRedemption;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Subscription;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Takes a subscription's coupon off: invoices from now on come without it;
 * those issued keep it. With $reissue (the payer taking it off before
 * paying), the block invoice waiting to be paid is issued again without it,
 * as ApplyCoupon issues it again with one (only while no money may be on
 * its way).
 */
final class RemoveCoupon
{
    public function __construct(private readonly ReissueBlock $reissue) {}

    public function handle(string $subscriptionId, ?string $actorId, bool $reissue = false): ?Invoice
    {
        return DB::transaction(function () use ($subscriptionId, $actorId, $reissue) {
            $subscription = $reissue ? Subscription::query()->lockForUpdate()->findOrFail($subscriptionId) : null;
            $redemption = CouponRedemption::query()->with('coupon')->where('subscription_id', $subscriptionId)->whereNull('removed_at')->lockForUpdate()->first();
            if ($redemption === null) {
                throw ValidationException::withMessages(['coupon' => __('billing::discounts.errors.no_coupon')]);
            }
            $waiting = $subscription !== null ? $this->reissue->waiting($subscription) : null;
            $redemption->update(['removed_at' => CarbonImmutable::now(), 'removed_by' => $actorId]);
            $reissued = $waiting !== null
                ? $this->reissue->again($subscription, $waiting, __('billing::discounts.reissue_removed', ['code' => $redemption->coupon->code]), $actorId)
                : null;
            app(AuditTrail::class)->record('billing.coupon_removed', 'subscription', $subscriptionId, array_filter([
                'code' => $redemption->coupon->code, 'voided' => $waiting?->number, 'invoice' => $reissued?->number,
            ]));

            return $reissued;
        });
    }
}
