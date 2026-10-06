<?php

namespace Modules\Billing\Payments\Gateways;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\Billing\Payments\CheckoutRequest;
use Modules\Billing\Payments\CheckoutSession;
use Modules\Billing\Payments\CheckoutState;
use Modules\Billing\Payments\GatewayEvent;
use Modules\Billing\Payments\GatewayException;
use Modules\Billing\Payments\PaymentGateway;
use Modules\SharedKernel\Money\Money;

/**
 * Moyasar (Saudi Arabia): mada, Visa/Mastercard, Apple Pay and STC Pay on
 * Moyasar's hosted invoice page. Basic auth with the secret key; webhooks
 * carry the shared secret in their body (secret_token).
 */
final class MoyasarGateway implements PaymentGateway
{
    /** @param  array<string, string>  $credentials  secret_key, webhook_secret */
    public function __construct(private readonly array $credentials) {}

    public function refuses(Money $amount): ?string
    {
        return null;   // the account's currencies (SAR unless the academy's contract says more) are checked before
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $invoice = $this->send(fn (PendingRequest $http) => $http->post('/v1/invoices', [
            'amount' => $request->amount->minor,
            'currency' => $request->amount->currency,
            'description' => $request->description,
            'callback_url' => $request->returnUrl,
            'success_url' => $request->returnUrl,
            'back_url' => $request->cancelUrl,
            'expired_at' => $request->expiresAt->utc()->toIso8601ZuluString(),
            'metadata' => ['checkout_id' => $request->checkoutId, 'invoice' => $request->invoiceNumber],
        ]));
        $url = (string) $invoice['url'];

        return new CheckoutSession((string) $invoice['id'], str_starts_with($request->locale, 'ar') && ! str_contains($url, 'lang=')
            ? $url.(str_contains($url, '?') ? '&' : '?').'lang=ar' : $url);
    }

    public function fetch(string $providerRef): CheckoutState
    {
        $invoice = $this->send(fn (PendingRequest $http) => $http->get('/v1/invoices/'.rawurlencode($providerRef)));
        $amount = isset($invoice['amount'], $invoice['currency']) ? Money::of((int) $invoice['amount'], (string) $invoice['currency']) : null;
        $paid = collect($invoice['payments'] ?? [])->firstWhere('status', 'paid');

        return match ($invoice['status'] ?? null) {
            'paid' => new CheckoutState(CheckoutState::PAID, $amount, $paid['id'] ?? $providerRef),
            'expired' => new CheckoutState(CheckoutState::EXPIRED, $amount),
            'failed', 'canceled' => new CheckoutState(CheckoutState::FAILED, $amount, failure: (string) $invoice['status']),
            default => new CheckoutState(CheckoutState::OPEN, $amount),
        };
    }

    public function parseWebhook(Request $request): ?GatewayEvent
    {
        $secret = (string) ($this->credentials['webhook_secret'] ?? '');
        $event = $request->json()->all();
        if ($secret === '' || ! hash_equals($secret, (string) ($event['secret_token'] ?? '')) || ! isset($event['id'], $event['type'])) {
            return null;
        }
        unset($event['secret_token']);   // not kept in the inbox
        $payment = $event['data'] ?? [];

        return new GatewayEvent((string) $event['id'], (string) $event['type'], $payment['invoice_id'] ?? null, $event);
    }

    public function check(): ?string
    {
        try {
            $this->send(fn (PendingRequest $http) => $http->get('/v1/invoices', ['page' => 1]));

            return null;
        } catch (GatewayException $e) {
            return $e->getMessage();
        }
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        $http = Http::baseUrl((string) config('billing.payments.moyasar.base'))
            ->withBasicAuth((string) ($this->credentials['secret_key'] ?? ''), '')
            ->asJson()->acceptJson()->timeout((int) config('billing.payments.timeout'));

        try {
            $response = $call($http);
        } catch (ConnectionException $e) {
            throw new GatewayException('Moyasar could not be reached: '.$e->getMessage(), previous: $e);
        }
        if ($response->failed()) {
            throw new GatewayException('Moyasar: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        return (array) $response->json();
    }
}
