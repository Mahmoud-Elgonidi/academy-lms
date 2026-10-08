<?php

namespace Modules\Billing\Discounts;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\Coupon;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Whether a code can go on a subscription now: it exists and is on, today
 * (on the academy's calendar) is within its dates, it has uses left, it is
 * for this plan and, an amount, in this currency, and this payer has not
 * used it before. Says why not, in words for the person typing it.
 */
final class CouponCheck
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** @throws ValidationException under "coupon" */
    public function usable(string $code, string $payerId, string $currency, string $planId): Coupon
    {
        $coupon = Coupon::byCode($code);
        $today = CarbonImmutable::now($this->tenant->require()->defaultTimezone)->toDateString();

        $why = match (true) {
            $coupon === null => 'not_found',
            ! $coupon->is_active => 'inactive',
            $coupon->starts_on !== null && $coupon->starts_on->toDateString() > $today => 'not_yet',
            $coupon->ends_on !== null && $coupon->ends_on->toDateString() < $today => 'expired',
            $coupon->max_uses !== null && $coupon->uses() >= $coupon->max_uses => 'used_up',
            ! $coupon->forPlan($planId) => 'plan',
            $coupon->currency !== null && $coupon->currency !== $currency => 'currency',
            $this->usedBy($coupon, $payerId) => 'used_before',
            default => null,
        };
        if ($why !== null) {
            throw ValidationException::withMessages(['coupon' => __('billing::discounts.errors.'.$why, [
                'code' => Coupon::normalize($code),
                'date' => $coupon?->starts_on?->locale(app()->getLocale())->translatedFormat('j F'),
                'currency' => $coupon?->currency,
            ])]);
        }

        return $coupon;
    }

    /** Once per payer: on any of their accounts, still on it or having taken something off. */
    private function usedBy(Coupon $coupon, string $payerId): bool
    {
        $accounts = DB::table('billing_accounts')->where('payer_id', $payerId)->pluck('id');

        return $accounts->isNotEmpty() && $coupon->counted()->whereIn('billing_account_id', $accounts)->exists();
    }
}
