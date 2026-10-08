<?php

namespace Modules\Billing\Discounts;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Domain\Discount;
use Modules\Billing\Domain\Discounts;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Models\CouponRedemption;
use Modules\Billing\Models\Subscription;
use Modules\SharedKernel\Money\Money;

/**
 * The lines that come off a subscription's invoice: the family discount
 * for the student's place among the payer's children, and, on a block, the
 * subscription's coupon while it has turns left. Each is its own line on
 * the invoice (a negative amount), saying what it was, so the invoice
 * keeps it as it was given.
 */
final class InvoiceDiscounts
{
    public function __construct(private readonly SiblingRank $ranks) {}

    /** @return list<array{kind: string, description_ar: string, description_en: string, quantity: int, unit_minor: int, amount_minor: int, meta: array<string, mixed>}> */
    public function lines(Subscription $subscription, int $baseMinor, bool $withCoupon): array
    {
        $studentId = (string) DB::table('enrollments')->where('id', $subscription->enrollment_id)->value('student_id');
        $siblingBp = BillingSettings::current()->siblingBp($this->ranks->of($subscription->billing_account_id, $studentId));

        $redemption = $withCoupon ? CouponRedemption::liveFor($subscription->id) : null;
        if ($redemption !== null && (! $redemption->hasTurnsLeft() || ($redemption->coupon->currency !== null && $redemption->coupon->currency !== $subscription->currency))) {
            $redemption = null;
        }

        return array_map(
            fn (Discount $d) => $this->line($d, $d->source === Discount::COUPON ? $redemption : null),
            Discounts::on($baseMinor, $siblingBp, $redemption?->coupon->terms()),
        );
    }

    /** @return array{kind: string, description_ar: string, description_en: string, quantity: int, unit_minor: int, amount_minor: int, meta: array<string, mixed>} */
    private function line(Discount $discount, ?CouponRedemption $redemption): array
    {
        $coupon = $redemption?->coupon;
        $describe = fn (string $locale) => $coupon === null
            ? __('billing::billing.lines.sibling', ['rate' => Coupon::rate((int) $discount->rateBp)], $locale)
            : __('billing::billing.lines.coupon', ['code' => $coupon->code, 'value' => $this->value($coupon, $locale)], $locale);

        return [
            'kind' => 'discount',
            'description_ar' => $describe('ar'),
            'description_en' => $describe('en'),
            'quantity' => 1,
            'unit_minor' => -$discount->minor,
            'amount_minor' => -$discount->minor,
            'meta' => array_filter([
                'source' => $discount->source,
                'rate_bp' => $discount->rateBp,
                'coupon_id' => $coupon?->id,
                'code' => $coupon?->code,
                'redemption_id' => $redemption?->id,
            ], fn ($v) => $v !== null),
        ];
    }

    private function value(Coupon $coupon, string $locale): string
    {
        return $coupon->kind === 'percent'
            ? Coupon::rate((int) $coupon->percent_bp)
            : Money::of((int) $coupon->amount_minor, (string) $coupon->currency)->format($locale);
    }
}
