<?php

namespace Modules\Billing\Actions\Discounts;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Discounts\CouponCheck;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Models\CouponRedemption;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Models\PaymentProof;
use Modules\Billing\Models\Subscription;

/**
 * The payer puts a coupon on an invoice, or takes it off, before paying
 * (docs/architecture/02, section 27, J3): on the signed pay page or in the
 * family's app. Only a course's own block invoice (one course, no extra
 * lessons) that is issued with nothing paid, no proof waiting and no online
 * payment open: the coupon goes on the course (ApplyCoupon, checked by
 * CouponCheck in the payer's words), and the invoice is issued again with
 * it (an issued invoice never changes), so the answer is the new invoice.
 * The trial's offer stays once on (it is not taken off, so it is not lost).
 */
final class PayerCoupon
{
    public function __construct(
        private readonly ApplyCoupon $apply,
        private readonly RemoveCoupon $remove,
        private readonly CouponCheck $check,
    ) {}

    /**
     * Why a coupon cannot be typed on this invoice now (a reason key under billing::discounts.payer), or null when it can.
     * The payer is checked by the caller (a stranger's invoice reads as not found).
     */
    public static function whyNot(Invoice $invoice): ?string
    {
        if ($invoice->status !== 'issued') {
            return 'not_open';
        }
        $invoice->loadMissing('lines');
        $courses = $invoice->lines->pluck('subscription_id')->filter()->unique();
        $now = CarbonImmutable::now();

        return match (true) {
            $invoice->paid_minor > 0 => 'paid_some',
            PaymentProof::query()->where('invoice_id', $invoice->id)->where('status', 'pending')->exists() => 'proof_waiting',
            PaymentCheckout::query()->where('invoice_id', $invoice->id)->where('status', 'open')->where('expires_at', '>', $now)->exists() => 'checkout_open',
            $courses->count() > 1 => 'family_invoice',
            ! $invoice->lines->contains('kind', 'block') || $invoice->lines->contains('kind', 'extra') => 'not_block',
            default => null,
        };
    }

    /** The coupon on this invoice now (its code, and whether it is the payer's own offer, which stays). */
    public static function onInvoice(Invoice $invoice): ?array
    {
        $line = $invoice->loadMissing('lines')->lines->first(fn ($l) => $l->kind === 'discount' && isset($l->meta['code']));
        if ($line === null) {
            return null;
        }
        $coupon = Coupon::query()->find($line->meta['coupon_id'] ?? null);

        return ['code' => (string) $line->meta['code'], 'personal' => (bool) $coupon?->isPersonal()];
    }

    /** @return Invoice the invoice issued again with the coupon */
    public function apply(Invoice $invoice, string $payerId, string $code): Invoice
    {
        $code = Coupon::normalize($code);
        if (! preg_match('/^[A-Z0-9_-]{3,32}$/', $code)) {
            throw ValidationException::withMessages(['code' => __('billing::discounts.payer.code')]);
        }

        return DB::transaction(function () use ($invoice, $payerId, $code) {
            [$invoice, $subscription] = $this->open($invoice, $payerId);
            $live = CouponRedemption::liveFor($subscription->id);
            if ($live !== null) {
                $this->refuse('has_coupon', ['code' => $live->coupon->code]);
            }
            $this->check->usable($code, $payerId, $subscription->currency, $subscription->plan_id, forPayer: true);
            $reissued = $this->apply->handle($subscription->id, $code, $payerId, reissue: true)['reissued'];

            return $reissued ?? $invoice;
        });
    }

    /** @return Invoice the invoice issued again without it */
    public function remove(Invoice $invoice, string $payerId): Invoice
    {
        return DB::transaction(function () use ($invoice, $payerId) {
            [$invoice, $subscription] = $this->open($invoice, $payerId);
            $live = CouponRedemption::liveFor($subscription->id);
            if ($live === null) {
                $this->refuse('no_coupon');
            }
            if ($live->coupon->isPersonal()) {
                $this->refuse('offer_stays');
            }

            return $this->remove->handle($subscription->id, $payerId, reissue: true) ?? $invoice;
        });
    }

    /** @return array{0: Invoice, 1: Subscription} the invoice under lock, and its course */
    private function open(Invoice $invoice, string $payerId): array
    {
        $invoice = Invoice::lock($invoice->id);
        if (DB::table('billing_accounts')->where('id', $invoice->billing_account_id)->value('payer_id') !== $payerId) {
            $this->refuse('not_payer');
        }
        if (($why = self::whyNot($invoice)) !== null) {
            $this->refuse($why);
        }
        $subscription = Subscription::query()->lockForUpdate()->findOrFail($invoice->lines->firstWhere('kind', 'block')->subscription_id);
        if ($subscription->status === 'cancelled') {
            $this->refuse('not_open');
        }

        return [$invoice, $subscription];
    }

    /** @param  array<string, string>  $replace */
    private function refuse(string $why, array $replace = []): never
    {
        throw ValidationException::withMessages(['coupon' => __('billing::discounts.payer.'.$why, $replace)]);
    }
}
