<?php

namespace Modules\Billing\Actions\Discounts;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\IssueBlockInvoice;
use Modules\Billing\Actions\VoidInvoice;
use Modules\Billing\Discounts\CouponCheck;
use Modules\Billing\Models\BillingAccount;
use Modules\Billing\Models\CouponRedemption;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Models\PaymentProof;
use Modules\Billing\Models\Subscription;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Puts a coupon on a subscription: its next invoices come out with it
 * taken off. An invoice already issued never changes, so the block waiting
 * to be paid takes it only by being issued again: voided (a credit note for
 * a tax invoice) and issued anew, which needs nothing paid on it, no proof
 * waiting and no online payment under way.
 */
final class ApplyCoupon
{
    public function __construct(
        private readonly CouponCheck $check,
        private readonly VoidInvoice $void,
        private readonly IssueBlockInvoice $issue,
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
            $coupon = $this->check->usable($code, $payerId, $subscription->currency, $subscription->plan_id);

            $waiting = $reissue ? $this->waitingBlock($subscription) : null;

            $redemption = CouponRedemption::query()->create([
                'coupon_id' => $coupon->id,
                'billing_account_id' => $subscription->billing_account_id,
                'subscription_id' => $subscription->id,
                'applied_by' => $actorId,
                'applied_at' => CarbonImmutable::now(),
            ]);

            $reissued = null;
            if ($waiting !== null) {
                $block = (int) $waiting->lines->firstWhere('kind', 'block')->meta['block_no'];
                $this->void->handle($waiting->id, __('billing::discounts.reissue_reason', ['code' => $coupon->code]), $actorId);
                [$ar, $en] = $subscription->subjectNames();
                $reissued = $this->issue->handle($subscription, $block, $ar, $en, $actorId);
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

    /** The block invoice waiting to be paid, if it can be issued again; refuses when money may be on its way. */
    private function waitingBlock(Subscription $subscription): ?Invoice
    {
        $invoice = Invoice::query()->with('lines')->where('subscription_id', $subscription->id)->where('status', 'issued')
            ->whereHas('lines', fn ($q) => $q->where('kind', 'block'))->latest('issued_at')->first();
        if ($invoice === null) {
            return null;
        }
        if ($invoice->paid_minor > 0
            || PaymentProof::query()->where('invoice_id', $invoice->id)->where('status', 'pending')->exists()
            || PaymentCheckout::query()->where('invoice_id', $invoice->id)->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['coupon' => __('billing::discounts.errors.invoice_busy', ['number' => $invoice->number])]);
        }

        return $invoice;
    }
}
