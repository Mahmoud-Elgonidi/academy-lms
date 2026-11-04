<?php

namespace Modules\Billing\Actions\AutoRenew;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\Payments\SettleCheckout;
use Modules\Billing\Models\AutoCharge;
use Modules\Billing\Models\BillingAccount;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Models\PaymentProof;
use Modules\Billing\Payments\Cards\CardFailure;
use Modules\Billing\Payments\Cards\ChargeResult;
use Modules\Billing\Payments\Cards\SavedCharge;
use Modules\Billing\Payments\GatewayManager;
use Modules\Billing\Payments\PayLinks;
use Modules\Billing\Support\AutoCharges;
use Modules\Platform\Contracts\BrandingApi;
use Throwable;

/**
 * Charges a renewal invoice to the payer's saved card at its moment
 * (docs/architecture/02, section 27, J5b), once: under the payer's lock (one
 * charge at a time per payer), the automatic charge is claimed (scheduled or
 * retry → charging) with one checkout of kind "saved", then the gateway is
 * asked to charge (outside the transaction). Paid, pending or unknown, the
 * money is recorded only through SettleCheckout (the gateway's word);
 * declined or wanting the payer (3-D Secure), the checkout fails and
 * AutoCharges says what next (a retry a day later for a soft decline, else
 * the pay link).
 *
 * Nothing is charged, and the charge is cancelled, when the invoice was paid
 * (also in part, by hand), voided, has a transfer receipt waiting, changed
 * amount, or none of its courses is on (paused, ended); when automatic
 * renewal was turned off or the card removed. No card for its currency, a
 * card expired or its gateway account off: skipped, and the payer gets the pay
 * link. A payment page the payer opened on it meanwhile, or another charge of
 * theirs under way: it waits for the next hour.
 */
final class ChargeAutoRenewal
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly SettleCheckout $settle,
        private readonly AutoCharges $autoCharges,
        private readonly PayLinks $links,
        private readonly BrandingApi $branding,
        private readonly FollowUpAutoRenewals $followUp,
    ) {}

    public function handle(string $autoChargeId, ?CarbonImmutable $now = null): ?PaymentCheckout
    {
        $now ??= CarbonImmutable::now();
        $claim = DB::transaction(function () use ($autoChargeId, $now) {
            $charge = AutoCharge::query()->find($autoChargeId);
            if ($charge === null) {
                return null;
            }
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['auto-charge:'.$charge->payer_id]);
            $charge = AutoCharge::lock($autoChargeId);
            if (! in_array($charge->status, [AutoCharge::SCHEDULED, AutoCharge::RETRY], true) || $charge->nextAt()?->greaterThan($now)) {
                return null;
            }
            $invoice = Invoice::lock($charge->invoice_id);
            $why = $this->cancelReason($charge, $invoice);
            if ($why !== null) {
                $this->autoCharges->cancel($charge, $why);

                return null;
            }
            $method = PaymentMethod::query()->with('account')->find($charge->payment_method_id);
            $skip = $this->skipReason($method, $invoice, $now);
            if ($skip !== null) {
                return ['skip' => $skip, 'charge' => $charge];
            }
            // Wait for the next hour: a payment page the payer opened on it, or another charge of theirs under way.
            $busy = PaymentCheckout::query()->where('invoice_id', $invoice->id)->where('status', 'open')->where('expires_at', '>', $now)->exists()
                || AutoCharge::query()->where('payer_id', $charge->payer_id)->where('status', AutoCharge::CHARGING)->whereKeyNot($charge->id)->exists();
            if ($busy) {
                return null;
            }

            $checkout = PaymentCheckout::query()->create([
                'invoice_id' => $invoice->id, 'gateway_account_id' => $method->gateway_account_id, 'payer_id' => $charge->payer_id,
                'channel' => 'auto', 'kind' => 'saved', 'payment_method_id' => $method->id,
                'amount_minor' => $charge->amount_minor, 'currency' => $charge->currency,
                'expires_at' => $now->addDay(),
            ]);
            $charge->update(['status' => AutoCharge::CHARGING, 'attempts' => $charge->attempts + 1, 'checkout_id' => $checkout->id, 'next_attempt_at' => null]);

            return ['checkout' => $checkout, 'method' => $method, 'invoice' => $invoice, 'charge' => $charge];
        });
        if ($claim === null) {
            return null;
        }
        if (isset($claim['skip'])) {
            $this->followUp->skip($claim['charge'], $claim['skip']);

            return null;
        }

        /** @var PaymentCheckout $checkout */
        $checkout = $claim['checkout'];
        $method = $claim['method'];
        $invoice = $claim['invoice'];
        try {
            $result = $this->gateways->for($method->account)->chargeSaved(new SavedCharge(
                checkoutId: $checkout->id,
                amount: $checkout->money(),
                tokens: (array) $method->tokens,
                description: __('billing::payments.description', ['number' => $invoice->number, 'academy' => $this->branding->current()->name()]),
                invoiceNumber: (string) $invoice->number,
                webhookUrl: $this->links->webhook($method->account),
                returnUrl: $this->links->invoice($invoice, $checkout->payer_id),
            ));
        } catch (Throwable $e) {
            report($e);
            $result = ChargeResult::unknown();
        }
        if ($result->ref !== null) {
            $checkout->update(['provider_ref' => mb_substr($result->ref, 0, 120)]);
        }

        if (in_array($result->status, [ChargeResult::FAILED, ChargeResult::ACTION_REQUIRED], true)) {
            $checkout->update(['status' => 'failed', 'failure' => ($result->failure ?? CardFailure::Declined)->value]);
            $this->autoCharges->checkoutEnded($checkout->refresh());

            return $checkout;
        }
        // Paid, pending or unknown: the gateway's own word decides, now or later (the webhook, the hourly inquiry, the sweeper).
        try {
            return $this->settle->handle($checkout->refresh());
        } catch (Throwable $e) {
            report($e);

            return $checkout;
        }
    }

    /** Why this charge should not happen at all any more, or null. */
    private function cancelReason(AutoCharge $charge, Invoice $invoice): ?string
    {
        return match (true) {
            $invoice->status === 'void' => 'voided',
            $invoice->status === 'paid' || $invoice->outstanding()->minor <= 0 => 'paid',
            $invoice->paid_minor > 0 => 'paid_by_hand',
            $invoice->outstanding()->minor !== $charge->amount_minor || $invoice->currency !== $charge->currency => 'changed',
            PaymentProof::query()->where('invoice_id', $invoice->id)->where('status', 'pending')->exists() => 'proof',
            ! BillingAccount::query()->whereKey($invoice->billing_account_id)->where('auto_renew', true)->exists() => 'turned_off',
            $charge->payment_method_id === null || PaymentMethod::query()->live()->whereKey($charge->payment_method_id)->doesntExist() => 'card_removed',
            FollowUpAutoRenewals::coursesOn($invoice->id) === [] => 'course_off',
            default => null,
        };
    }

    /** Why the card cannot be charged for this invoice (the payer gets the pay link instead), or null. */
    private function skipReason(?PaymentMethod $method, Invoice $invoice, CarbonImmutable $now): ?string
    {
        if ($method === null || $method->account === null || ! $method->account->is_active) {
            return 'gateway_off';
        }
        if (! $method->account->takes($invoice->currency)) {
            return 'currency';
        }
        if ($method->expiresBefore($now)) {
            return 'card_expired';
        }
        try {
            return $this->gateways->for($method->account)->savesCards() ? null : 'gateway_off';
        } catch (Throwable) {
            return 'gateway_off';
        }
    }
}
