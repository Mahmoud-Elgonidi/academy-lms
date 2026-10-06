<?php

namespace Modules\Billing\Actions\Payments;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Payments\CheckoutState;
use Modules\Billing\Payments\GatewayManager;

/**
 * Asks the gateway where a checkout stands, and acts on its answer only:
 * paid records the payment once (the invoice's minutes, the enrollment, the
 * waiting lessons follow, as for any payment); failed and expired close it.
 * Called on the payer's return, on each webhook, and by the sweeper. Money
 * that arrives for an invoice already settled another way is marked for a
 * refund, never recorded twice.
 */
final class SettleCheckout
{
    public function __construct(private readonly GatewayManager $gateways, private readonly RecordPayment $record) {}

    public function handle(PaymentCheckout $checkout): PaymentCheckout
    {
        if (in_array($checkout->status, ['paid', 'refund_due'], true) || $checkout->provider_ref === null) {
            return $checkout;
        }
        $account = $checkout->account()->firstOrFail();
        $state = $this->gateways->for($account)->fetch($checkout->provider_ref);

        return DB::transaction(function () use ($checkout, $state, $account) {
            $checkout = PaymentCheckout::lock($checkout->id);
            if (in_array($checkout->status, ['paid', 'refund_due'], true)) {
                return $checkout;
            }

            if ($state->status === CheckoutState::PAID) {
                if ($state->amount !== null && ($state->amount->minor !== $checkout->amount_minor || $state->amount->currency !== $checkout->currency)) {
                    $checkout->update(['status' => 'refund_due', 'failure' => 'amount_mismatch', 'provider_payment_ref' => $state->paymentRef, 'paid_at' => CarbonImmutable::now()]);
                    report(new \RuntimeException("Checkout {$checkout->id}: the gateway charged {$state->amount->minor} {$state->amount->currency}, not {$checkout->amount_minor} {$checkout->currency}."));

                    return $checkout;
                }
                $invoice = Invoice::lock($checkout->invoice_id);
                if ($invoice->status !== 'issued' || $invoice->outstanding()->minor < $checkout->amount_minor) {
                    $checkout->update(['status' => 'refund_due', 'failure' => 'invoice_settled', 'provider_payment_ref' => $state->paymentRef, 'paid_at' => CarbonImmutable::now()]);

                    return $checkout;
                }
                $this->record->handle($invoice->id, Payment::ONLINE, $checkout->money(), $state->paymentRef, CarbonImmutable::now(),
                    $account->label, null, $checkout->id);
                $checkout->update(['status' => 'paid', 'provider_payment_ref' => $state->paymentRef, 'paid_at' => CarbonImmutable::now(), 'failure' => null]);
            } elseif ($state->status === CheckoutState::FAILED && $checkout->status === 'open') {
                $checkout->update(['status' => 'failed', 'failure' => $state->failure !== null ? mb_substr($state->failure, 0, 200) : null]);
            } elseif ($state->status === CheckoutState::EXPIRED && $checkout->status === 'open') {
                $checkout->update(['status' => 'expired']);
            }

            return $checkout;
        });
    }
}
