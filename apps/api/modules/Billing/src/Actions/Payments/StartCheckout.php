<?php

namespace Modules\Billing\Actions\Payments;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Payments\CheckoutRequest;
use Modules\Billing\Payments\GatewayException;
use Modules\Billing\Payments\GatewayManager;
use Modules\Billing\Payments\PayLinks;
use Modules\Platform\Contracts\BrandingApi;

/**
 * Opens a gateway's page to pay what is left on an invoice. Pressing "pay"
 * twice brings back the page already open; nothing is charged here, the
 * money is recorded only when the gateway says so (SettleCheckout).
 */
final class StartCheckout
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly PayLinks $links,
        private readonly BrandingApi $branding,
    ) {}

    /** @param  string  $channel  family (signed in) | link (the invoice's pay link) */
    public function handle(Invoice $invoice, GatewayAccount $account, ?string $payerId, string $channel): PaymentCheckout
    {
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

        $open = PaymentCheckout::query()->where('invoice_id', $invoice->id)->where('gateway_account_id', $account->id)
            ->where('status', 'open')->where('amount_minor', $amount->minor)->whereNotNull('url')
            ->where('expires_at', '>', CarbonImmutable::now()->addMinutes(5))->latest()->first();
        if ($open !== null) {
            return $open;
        }

        $checkout = PaymentCheckout::query()->create([
            'invoice_id' => $invoice->id, 'gateway_account_id' => $account->id, 'payer_id' => $payerId, 'channel' => $channel,
            'amount_minor' => $amount->minor, 'currency' => $amount->currency,
            'expires_at' => CarbonImmutable::now()->addMinutes((int) config('billing.payments.checkout_minutes')),
        ]);
        $payer = DB::table('billing_accounts as ba')->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'ba.academy_id')->on('u.id', '=', 'ba.payer_id'))
            ->where('ba.id', $invoice->billing_account_id)->first(['u.name', 'u.email', 'u.phone_e164', 'u.locale']);
        $back = $this->links->returnTo($checkout);

        try {
            $session = $gateway->createCheckout(new CheckoutRequest(
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
            ));
        } catch (GatewayException $e) {
            $checkout->update(['status' => 'failed', 'failure' => mb_substr($e->getMessage(), 0, 200)]);
            report($e);

            throw ValidationException::withMessages(['account' => __('billing::payments.errors.gateway')]);
        }

        $checkout->update(['provider_ref' => $session->ref, 'url' => $session->url]);

        return $checkout;
    }
}
