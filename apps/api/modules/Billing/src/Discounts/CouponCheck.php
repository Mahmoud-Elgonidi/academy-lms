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
 * used it before. A personal coupon (a trial offer) is this payer's alone
 * and ends at its moment, not only its day. Says why not, in words for the
 * person typing it.
 */
final class CouponCheck
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** @throws ValidationException under "coupon"; $forPayer words it for the payer typing it (what to do next) */
    public function usable(string $code, string $payerId, string $currency, string $planId, bool $forPayer = false): Coupon
    {
        $coupon = Coupon::byCode($code);
        $today = CarbonImmutable::now($this->tenant->require()->defaultTimezone)->toDateString();

        $why = match (true) {
            // Someone else's personal offer reads as no such code: it never says it exists, nor whose it is.
            $coupon === null, $coupon->payer_id !== null && $coupon->payer_id !== $payerId => 'not_found',
            ! $coupon->is_active => 'inactive',
            $coupon->expires_at !== null && ! $coupon->expires_at->isFuture() => 'expired',
            $coupon->starts_on !== null && $coupon->starts_on->toDateString() > $today => 'not_yet',
            $coupon->ends_on !== null && $coupon->ends_on->toDateString() < $today => 'expired',
            $coupon->max_uses !== null && $coupon->uses() >= $coupon->max_uses => 'used_up',
            ! $coupon->forPlan($planId) => 'plan',
            $coupon->currency !== null && $coupon->currency !== $currency => 'currency',
            $this->usedBy($coupon, $payerId) => 'used_before',
            default => null,
        };
        if ($why !== null) {
            throw ValidationException::withMessages(['coupon' => __('billing::discounts.'.($forPayer ? 'payer_errors.' : 'errors.').$why, [
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
