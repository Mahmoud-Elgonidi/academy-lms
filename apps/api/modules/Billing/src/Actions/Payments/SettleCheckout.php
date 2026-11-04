<?php

namespace Modules\Billing\Actions\Payments;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\Cards\KeepSavedCard;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Payments\CheckoutState;
use Modules\Billing\Payments\GatewayManager;
use Modules\Billing\Payments\PaymentGateway;
use Modules\Billing\Support\AutoCharges;
use Throwable;

/**
 * Asks the gateway where a checkout stands, and acts on its answer only:
 * paid records the payment once (the invoice's minutes, the enrollment, the
 * waiting lessons follow, as for any payment); failed and expired close it.
 * Called on the payer's return, on each webhook, and by the sweeper. Money
 * that arrives for an invoice already settled another way is marked for a
 * refund, never recorded twice.
 *
 * A charge on a saved card (kind "saved", J5b) is settled the same way, asked
 * as the gateway's charge (fetchSaved; found by our checkout's id when its
 * answer never came back), and its automatic charge hears how it ended
 * (AutoCharges). A paid page that asked to save the card keeps the card the
 * gateway itself kept (KeepSavedCard), never one the browser named.
 */
final class SettleCheckout
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly RecordPayment $record,
        private readonly KeepSavedCard $keep,
        private readonly AutoCharges $autoCharges,
    ) {}

    public function handle(PaymentCheckout $checkout): PaymentCheckout
    {
        if (in_array($checkout->status, ['paid', 'refund_due'], true)) {
            return $checkout;
        }
        $saved = $checkout->kind === 'saved';
        $account = $checkout->account()->firstOrFail();
        $gateway = $this->gateways->for($account);
        if ($checkout->provider_ref === null) {
            // A charge on a saved card we never heard back about: does the gateway know it by our id?
            $found = $saved ? $gateway->findSaved($checkout->id) : null;
            if ($found === null) {
                return $checkout;
            }
            $checkout->update(['provider_ref' => $found]);
        }
        $state = $saved ? $gateway->fetchSaved((string) $checkout->provider_ref) : $gateway->fetch((string) $checkout->provider_ref);
        $before = $checkout->status;

        $checkout = DB::transaction(function () use ($checkout, $state, $account) {
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

        if ($checkout->status !== $before) {
            $this->after($checkout, $gateway);
        }

        return $checkout;
    }

    /** What follows a checkout's end, outside the money's transaction: the card it saved, the automatic charge it was. */
    private function after(PaymentCheckout $checkout, PaymentGateway $gateway): void
    {
        if ($checkout->kind === 'saved') {
            $this->autoCharges->checkoutEnded($checkout);

            return;
        }
        if ($checkout->status === 'paid' && $checkout->save_card) {
            try {
                $card = $gateway->savedCard((string) $checkout->provider_ref);
                if ($card !== null) {
                    $this->keep->handle($checkout, $card);
                }
            } catch (Throwable $e) {
                report($e);     // the payment stands; the card was not kept (the payer can tick it again next time)
            }
        }
    }
}
