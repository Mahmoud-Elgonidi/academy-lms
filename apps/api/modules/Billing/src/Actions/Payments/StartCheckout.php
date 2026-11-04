<?php

namespace Modules\Billing\Actions\Payments;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Payments\Cards\Mandate;
use Modules\Billing\Payments\CheckoutRequest;
use Modules\Billing\Payments\GatewayException;
use Modules\Billing\Payments\GatewayManager;
use Modules\Billing\Payments\PayLinks;
use Modules\Platform\Contracts\BrandingApi;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;

/**
 * Opens a gateway's page to pay what is left on an invoice. Pressing "pay"
 * twice brings back the page already open; nothing is charged here, the
 * money is recorded only when the gateway says so (SettleCheckout).
 *
 * The payer may ask the gateway to keep their card and renew automatically
 * (J5b): only the payer themselves, on a gateway that keeps cards, having
 * ticked the mandate's current version; the card is typed on the gateway's
 * page and kept only from its answer. Should the gateway refuse to open a page
 * that keeps the card, a plain one opens (the payment matters more). While a
 * charge on their saved card is under way, no page opens (it would pay twice).
 */
final class StartCheckout
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly PayLinks $links,
        private readonly BrandingApi $branding,
        private readonly AcademyPlan $plan,
    ) {}

    /**
     * @param  string  $channel  family (signed in) | link (the invoice's pay link)
     * @param  ?string  $returnTo  portal | app: back from the gateway to the portal's /{locale}/pay/return (the app opens from there); null: the pay page
     */
    public function handle(Invoice $invoice, GatewayAccount $account, ?string $payerId, string $channel, ?string $returnTo = null, bool $saveCard = false, ?string $mandateVersion = null): PaymentCheckout
    {
        $this->plan->requireFeature(Feature::OnlinePayments);
        $amount = $invoice->outstanding();
        if ($invoice->status !== 'issued' || $amount->minor <= 0) {
            throw ValidationException::withMessages(['invoice' => __('billing::billing.errors.not_payable')]);
        }
        try {
            $gateway = $this->gateways->for($account);
        } catch (GatewayException) {
            $gateway = null;    // a gateway this server does not offer (the demo one in production)
        }
        $why = $gateway?->refuses($amount);
        if ($gateway === null || ! $account->is_active || ! $account->takes($invoice->currency) || $why !== null) {
            throw ValidationException::withMessages(['account' => $why ?? __('billing::payments.errors.not_here', ['currency' => $invoice->currency])]);
        }

        if (PaymentCheckout::query()->where('invoice_id', $invoice->id)->where('kind', 'saved')->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['invoice' => __('billing::cards.errors.charge_under_way')]);
        }
        if ($saveCard) {
            $payerOfInvoice = DB::table('billing_accounts')->where('id', $invoice->billing_account_id)->value('payer_id');
            if ($payerId === null || $payerId !== $payerOfInvoice || ! $gateway->savesCards()) {
                throw ValidationException::withMessages(['save_card' => __('billing::cards.errors.not_offered')]);
            }
            if ($mandateVersion !== Mandate::VERSION) {
                throw ValidationException::withMessages(['mandate_version' => __('billing::cards.errors.mandate_changed')]);
            }
        }

        $open = PaymentCheckout::query()->where('invoice_id', $invoice->id)->where('gateway_account_id', $account->id)
            ->where('kind', 'page')->where('save_card', $saveCard)
            ->where('status', 'open')->where('amount_minor', $amount->minor)->whereNotNull('url')
            ->when($returnTo === null, fn ($q) => $q->whereNull('return_to'), fn ($q) => $q->where('return_to', $returnTo))
            ->where('expires_at', '>', CarbonImmutable::now()->addMinutes(5))->latest()->first();
        if ($open !== null) {
            return $open;
        }

        $checkout = PaymentCheckout::query()->create([
            'invoice_id' => $invoice->id, 'gateway_account_id' => $account->id, 'payer_id' => $payerId, 'channel' => $channel, 'return_to' => $returnTo,
            'save_card' => $saveCard, 'mandate_version' => $saveCard ? Mandate::VERSION : null,
            'amount_minor' => $amount->minor, 'currency' => $amount->currency,
            'expires_at' => CarbonImmutable::now()->addMinutes((int) config('billing.payments.checkout_minutes')),
        ]);
        $payer = DB::table('billing_accounts as ba')->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'ba.academy_id')->on('u.id', '=', 'ba.payer_id'))
            ->where('ba.id', $invoice->billing_account_id)->first(['u.name', 'u.email', 'u.phone_e164', 'u.locale']);
        $back = $this->links->returnTo($checkout);

        $request = fn (bool $save) => new CheckoutRequest(
            checkoutId: $checkout->id,
            amount: $amount,
            description: __('billing::payments.description', ['number' => $invoice->number, 'academy' => $this->branding->current()->name()]),
            invoiceNumber: (string) $invoice->number,
            returnUrl: $back,
            cancelUrl: $back,
            webhookUrl: $this->links->webhook($account),
            expiresAt: $checkout->expires_at,
            locale: (string) ($payer->locale ?? app()->getLocale()),
            payerName: $payer->name ?? null,
            payerEmail: $payer->email ?? null,
            payerPhone: $payer->phone_e164 ?? null,
            saveCard: $save,
        );
        try {
            try {
                $session = $gateway->createCheckout($request($saveCard));
            } catch (GatewayException $e) {
                if (! $saveCard) {
                    throw $e;
                }
                report($e);     // it would not keep the card: a plain page, so the payer can still pay
                $checkout->update(['save_card' => false, 'mandate_version' => null]);
                $session = $gateway->createCheckout($request(false));
            }
        } catch (GatewayException $e) {
            $checkout->update(['status' => 'failed', 'failure' => mb_substr($e->getMessage(), 0, 200)]);
            report($e);

            throw ValidationException::withMessages(['account' => __('billing::payments.errors.gateway')]);
        }

        $checkout->update(['provider_ref' => $session->ref, 'url' => $session->url]);

        return $checkout;
    }
}
