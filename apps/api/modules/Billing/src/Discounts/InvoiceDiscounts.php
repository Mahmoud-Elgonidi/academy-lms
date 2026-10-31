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
 * keeps it as it was given. A course that joined a group late has the
 * lessons it missed taken off its first block ($lateJoin).
 */
final class InvoiceDiscounts
{
    public function __construct(private readonly SiblingRank $ranks) {}

    /** @return list<array{kind: string, description_ar: string, description_en: string, quantity: int, unit_minor: int, amount_minor: int, meta: array<string, mixed>}> */
    public function lines(Subscription $subscription, int $baseMinor, bool $withCoupon, bool $lateJoin = false): array
    {
        $studentId = (string) DB::table('enrollments')->where('id', $subscription->enrollment_id)->value('student_id');
        $siblingBp = BillingSettings::current()->siblingBp($this->ranks->of($subscription->billing_account_id, $studentId));
        $late = $lateJoin && $subscription->late_join_lessons > 0 ? [$subscription->late_join_lessons, (int) $subscription->plan->sessions_per_block] : null;

        return $this->compose($subscription, $baseMinor, $siblingBp, $withCoupon ? CouponRedemption::liveFor($subscription->id) : null, $late);
    }

    /**
     * The lines of each subscription's next block (its price, its coupon), as lines() gives them one by
     * one, read in a few queries for all of them (the revenue board's forecast).
     *
     * @param  iterable<Subscription>  $subscriptions
     * @return array<string, list<array{kind: string, description_ar: string, description_en: string, quantity: int, unit_minor: int, amount_minor: int, meta: array<string, mixed>}>> by subscription id
     */
    public function blockLines(iterable $subscriptions): array
    {
        $subscriptions = collect($subscriptions)->values();
        if ($subscriptions->isEmpty()) {
            return [];
        }
        $students = DB::table('enrollments')->whereIn('id', $subscriptions->pluck('enrollment_id')->unique()->values()->all())->pluck('student_id', 'id');
        $pair = fn (Subscription $s) => [(string) $s->billing_account_id, (string) ($students[$s->enrollment_id] ?? '')];
        $ranks = $this->ranks->ofMany($subscriptions->map($pair)->all());
        $settings = BillingSettings::current();
        $redemptions = CouponRedemption::query()->with('coupon')->whereIn('subscription_id', $subscriptions->pluck('id')->all())
            ->whereNull('removed_at')->get()->keyBy('subscription_id');

        $out = [];
        foreach ($subscriptions as $s) {
            $out[$s->id] = $this->compose($s, (int) $s->amount_minor, $settings->siblingBp($ranks[implode('|', $pair($s))]), $redemptions[$s->id] ?? null);
        }

        return $out;
    }

    /** @return list<array{kind: string, description_ar: string, description_en: string, quantity: int, unit_minor: int, amount_minor: int, meta: array<string, mixed>}> */
    /** @param  array{0: int, 1: int}|null  $lateJoin */
    private function compose(Subscription $subscription, int $baseMinor, int $siblingBp, ?CouponRedemption $redemption, ?array $lateJoin = null): array
    {
        if ($redemption !== null && (! $redemption->hasTurnsLeft() || ($redemption->coupon->currency !== null && $redemption->coupon->currency !== $subscription->currency))) {
            $redemption = null;
        }

        return array_map(
            fn (Discount $d) => $this->line($d, $d->source === Discount::COUPON ? $redemption : null),
            Discounts::on($baseMinor, $siblingBp, $redemption?->coupon->terms(), $lateJoin),
        );
    }

    /** @return array{kind: string, description_ar: string, description_en: string, quantity: int, unit_minor: int, amount_minor: int, meta: array<string, mixed>} */
    private function line(Discount $discount, ?CouponRedemption $redemption): array
    {
        $coupon = $redemption?->coupon;
        $describe = fn (string $locale) => $discount->source === Discount::LATE_JOIN
            ? __('billing::billing.lines.late_join', ['count' => $discount->lessons], $locale)
            : ($coupon === null
            ? __('billing::billing.lines.sibling', ['rate' => Coupon::rate((int) $discount->rateBp)], $locale)
            : __('billing::billing.lines.coupon', ['code' => $coupon->code, 'value' => $this->value($coupon, $locale)], $locale));

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
                'lessons' => $discount->lessons,
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
