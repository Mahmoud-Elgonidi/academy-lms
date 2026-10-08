<?php

namespace Modules\Billing\Queries;

use Illuminate\Validation\ValidationException;
use Modules\Billing\Contracts\DTOs\CourseBilling;
use Modules\Billing\Contracts\DTOs\PriceQuote;
use Modules\Billing\Discounts\CouponCheck;
use Modules\Billing\Discounts\SiblingRank;
use Modules\Billing\Domain\Discount;
use Modules\Billing\Domain\Discounts;
use Modules\Billing\Models\BillingAccount;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Models\CouponRedemption;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Models\Subscription;
use Modules\SharedKernel\Money\Money;

/**
 * A course's money side (its subscription, the coupon on it, extra lessons)
 * and what a first block would come to before enrolling, the same for the
 * back office, the family screens and the API.
 */
final class CourseBillingQuery
{
    public function __construct(private readonly SiblingRank $ranks, private readonly CouponCheck $coupons) {}

    public function subscriptionOf(string $enrollmentId): ?Subscription
    {
        return Subscription::query()->with('plan')->where('enrollment_id', $enrollmentId)->where('status', '<>', 'cancelled')->latest()->first();
    }

    public function forEnrollment(string $enrollmentId, bool $enrollmentActive = true): ?CourseBilling
    {
        $subscription = $this->subscriptionOf($enrollmentId);
        if ($subscription === null) {
            return null;
        }
        $redemption = CouponRedemption::liveFor($subscription->id);
        $blocks = $redemption?->coupon->blocks;
        $waiting = Invoice::query()->where('subscription_id', $subscription->id)->where('status', 'issued')->with('lines')->get();
        $extra = $waiting->first(fn (Invoice $i) => $i->lines->contains('kind', 'extra'));
        $block = $waiting->first(fn (Invoice $i) => $i->lines->contains('kind', 'block'));

        return new CourseBilling(
            subscriptionId: $subscription->id,
            lessonPrice: $subscription->lessonPrice(),
            sessionMinutes: (int) $subscription->plan->session_minutes,
            couponCode: $redemption?->coupon->code,
            couponValue: $redemption?->coupon->valueLabel(),
            couponTurnsLeft: $redemption === null || $blocks === null ? null : max(0, $blocks - $redemption->used()),
            waitingExtraInvoiceId: $extra?->id,
            waitingExtraNumber: $extra?->number,
            waitingBlockNumber: $block?->number,
            active: $enrollmentActive && $subscription->status !== 'cancelled',
            payerId: (string) BillingAccount::query()->whereKey($subscription->billing_account_id)->value('payer_id'),
        );
    }

    public function quote(string $planId, string $priceRegionId, string $payerId, string $studentId, ?string $couponCode): ?PriceQuote
    {
        $price = PlanPrice::query()->where('plan_id', $planId)->where('price_region_id', $priceRegionId)->first();
        if ($price === null) {
            return null;
        }
        $siblingBp = BillingSettings::current()->siblingBp($this->ranks->forPayer($payerId, $price->currency, $studentId));

        $coupon = null;
        $error = null;
        $note = null;
        if (filled($couponCode)) {
            try {
                $coupon = $this->coupons->usable((string) $couponCode, $payerId, $price->currency, $planId);
            } catch (ValidationException $e) {
                $error = $e->validator->errors()->first('coupon');
            }
        }

        $discounts = Discounts::on($price->amount_minor, $siblingBp, $coupon?->terms());
        if ($coupon !== null && ! in_array(Discount::COUPON, array_map(fn (Discount $d) => $d->source, $discounts), true)) {
            $note = __('billing::discounts.quote.sibling_bigger');     // kept for a later invoice
        }

        return new PriceQuote(
            price: Money::of($price->amount_minor, $price->currency),
            discounts: array_map(fn (Discount $d) => [
                'label' => $d->source === Discount::COUPON
                    ? __('billing::discounts.quote.coupon', ['code' => $coupon?->code, 'value' => $coupon?->valueLabel()])
                    : __('billing::discounts.quote.sibling', ['rate' => Coupon::rate((int) $d->rateBp)]),
                'amount' => Money::of($d->minor, $price->currency),
            ], $discounts),
            total: Money::of($price->amount_minor - Discounts::total($discounts), $price->currency),
            couponError: $error,
            couponNote: $note,
        );
    }
}
