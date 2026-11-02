<?php

namespace Modules\Billing\Payments;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The academy's own addresses for paying: on its host (its domain, its
 * look), signed where nobody signs in. An invoice's pay link goes in the
 * invoice message; the return link brings the payer back from a gateway;
 * the webhook link is where a gateway posts its events.
 */
final class PayLinks
{
    public function __construct(private readonly TenantDirectory $directory, private readonly TenantContext $tenants) {}

    /**
     * The invoice and its ways to pay, for the payer, without signing in (a WhatsApp or email link).
     *
     * @param  array<string, string>  $extra
     */
    public function invoice(Invoice $invoice, ?string $payerId = null, array $extra = []): string
    {
        $days = (int) config('billing.payments.link_days');

        return $this->base().URL::temporarySignedRoute('billing.pay', CarbonImmutable::now()->addDays($days),
            array_filter(['invoice' => $invoice->id, 'u' => $payerId] + $extra), absolute: false);
    }

    /** The invoice itself, to read and print, for the payer without signing in (with its QR code for a Saudi tax invoice). */
    public function document(Invoice $invoice, ?string $payerId = null): string
    {
        $days = (int) config('billing.payments.link_days');

        return $this->base().URL::temporarySignedRoute('billing.pay.document', CarbonImmutable::now()->addDays($days),
            array_filter(['invoice' => $invoice->id, 'u' => $payerId]), absolute: false);
    }

    /** Back from the gateway's page (paid, or not), to the host the payer left from: their session is there. */
    public function returnTo(PaymentCheckout $checkout): string
    {
        return $this->forBrowser(URL::temporarySignedRoute('billing.pay.return', $checkout->expires_at->addDays(2), ['checkout' => $checkout->id], absolute: false));
    }

    /**
     * A page of ours (a relative path) the payer's browser opens next: on the host they are on, or on the academy's
     * own when the call came to the API's shared host (the apps, the portal's server) or from the console, where a
     * browser would find no academy.
     */
    public function forBrowser(string $path): string
    {
        $host = app()->runningInConsole() || request()->is('api/*') ? $this->base() : request()->getSchemeAndHttpHost();

        return $host.$path;
    }

    /**
     * The portal's page after a gateway (/{locale}/pay/return), for a payment begun in the portal or the app: the
     * portal reads the invoice signed in (GET family/invoices/{id}) and, begun in the app, opens the app from there.
     */
    public function portalReturn(PaymentCheckout $checkout, string $locale): string
    {
        return $this->base().'/'.($locale === 'en' ? 'en' : 'ar').'/pay/return?'.http_build_query(array_filter([
            'invoice' => $checkout->invoice_id,
            'checkout' => $checkout->id,
            'from' => $checkout->return_to === 'app' ? 'app' : null,
        ]));
    }

    public function webhook(GatewayAccount $account): string
    {
        return $this->base().route('billing.webhooks', ['academy' => $account->academy_id, 'account' => $account->id], absolute: false);
    }

    private function base(): string
    {
        return rtrim($this->directory->baseUrl($this->tenants->require()), '/');
    }
}
