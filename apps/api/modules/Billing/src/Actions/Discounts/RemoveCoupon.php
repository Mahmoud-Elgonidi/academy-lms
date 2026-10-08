<?php

namespace Modules\Billing\Actions\Discounts;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\CouponRedemption;
use Modules\SharedKernel\Audit\AuditTrail;

/** Takes a subscription's coupon off: invoices from now on come without it; those issued keep it. */
final class RemoveCoupon
{
    public function handle(string $subscriptionId, ?string $actorId): void
    {
        DB::transaction(function () use ($subscriptionId, $actorId) {
            $redemption = CouponRedemption::query()->with('coupon')->where('subscription_id', $subscriptionId)->whereNull('removed_at')->lockForUpdate()->first();
            if ($redemption === null) {
                throw ValidationException::withMessages(['coupon' => __('billing::discounts.errors.no_coupon')]);
            }
            $redemption->update(['removed_at' => CarbonImmutable::now(), 'removed_by' => $actorId]);
            app(AuditTrail::class)->record('billing.coupon_removed', 'subscription', $subscriptionId, ['code' => $redemption->coupon->code]);
        });
    }
}
