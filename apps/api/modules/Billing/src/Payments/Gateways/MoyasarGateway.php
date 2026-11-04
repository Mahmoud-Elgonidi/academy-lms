<?php

namespace Modules\Billing\Payments\Gateways;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\Billing\Payments\Cards\CardFailure;
use Modules\Billing\Payments\Cards\ChargeResult;
use Modules\Billing\Payments\Cards\SavedCard;
use Modules\Billing\Payments\Cards\SavedCharge;
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
 *
 * Saved cards (J5b): the invoice asks Moyasar to keep the card (save_card;
 * not in Moyasar's published invoice reference, see section 27 J5b), the paid
 * payment's source carries the token (source.token), its brand, last four and
 * expiry come from GET /v1/tokens/{id}, and a renewal is a payment with
 * source type "token" (no 3-D Secure; given_id is our checkout's id, so a
 * charge we never heard back about is found by it, and never made twice).
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
        $invoice = $this->send(fn (PendingRequest $http) => $http->post('/v1/invoices', array_filter([
            'amount' => $request->amount->minor,
            'currency' => $request->amount->currency,
            'description' => $request->description,
            'callback_url' => $request->returnUrl,
            'success_url' => $request->returnUrl,
            'back_url' => $request->cancelUrl,
            'expired_at' => $request->expiresAt->utc()->toIso8601ZuluString(),
            'metadata' => ['checkout_id' => $request->checkoutId, 'invoice' => $request->invoiceNumber],
            'save_card' => $request->saveCard ? true : null,
        ], fn ($v) => $v !== null)));
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

    public function savesCards(): bool
    {
        return true;
    }

    public function savedCard(string $providerRef): ?SavedCard
    {
        $invoice = $this->send(fn (PendingRequest $http) => $http->get('/v1/invoices/'.rawurlencode($providerRef)));
        $token = (string) (collect($invoice['payments'] ?? [])->firstWhere('status', 'paid')['source']['token'] ?? '');
        if (! str_starts_with($token, 'token_')) {
            return null;
        }
        $card = $this->send(fn (PendingRequest $http) => $http->get('/v1/tokens/'.rawurlencode($token)));

        return new SavedCard(['token' => $token], (string) ($card['brand'] ?? 'card'), (string) ($card['last_four'] ?? ''),
            (int) ($card['month'] ?? 0), (int) ($card['year'] ?? 0));
    }

    public function chargeSaved(SavedCharge $charge): ChargeResult
    {
        try {
            $response = $this->http()->post('/v1/payments', [
                'given_id' => $charge->checkoutId,
                'amount' => $charge->amount->minor,
                'currency' => $charge->amount->currency,
                'description' => $charge->description,
                'callback_url' => $charge->returnUrl,
                'source' => ['type' => 'token', 'token' => (string) ($charge->tokens['token'] ?? ''), '3ds' => false, 'manual' => false],
                'metadata' => ['checkout_id' => $charge->checkoutId, 'invoice' => $charge->invoiceNumber],
            ]);
        } catch (ConnectionException) {
            return ChargeResult::unknown();
        }
        if ($response->serverError()) {
            return ChargeResult::unknown();
        }
        $payment = (array) $response->json();
        if ($response->failed()) {
            // The request itself was refused (a token no longer valid, an amount it would not take): nothing was charged.
            $why = CardFailure::fromCode((string) ($payment['type'] ?? ''), (string) ($payment['message'] ?? ''));

            return ChargeResult::failed($why === CardFailure::Declined ? CardFailure::InvalidCard : $why);
        }
        $ref = isset($payment['id']) ? (string) $payment['id'] : null;
        $source = (array) ($payment['source'] ?? []);

        return match ($payment['status'] ?? null) {
            'paid', 'captured' => new ChargeResult(ChargeResult::PAID, $ref),
            'initiated' => ! empty($source['transaction_url']) ? ChargeResult::failed(CardFailure::AuthenticationRequired, $ref) : new ChargeResult(ChargeResult::PENDING, $ref),
            'failed' => ChargeResult::failed(CardFailure::fromCode((string) ($source['response_code'] ?? ''), (string) ($source['message'] ?? '')), $ref),
            default => new ChargeResult(ChargeResult::PENDING, $ref),
        };
    }

    public function fetchSaved(string $ref): CheckoutState
    {
        $payment = $this->send(fn (PendingRequest $http) => $http->get('/v1/payments/'.rawurlencode($ref)));
        $amount = isset($payment['amount'], $payment['currency']) ? Money::of((int) $payment['amount'], (string) $payment['currency']) : null;
        $source = (array) ($payment['source'] ?? []);

        return match ($payment['status'] ?? null) {
            'paid', 'captured' => new CheckoutState(CheckoutState::PAID, $amount, (string) ($payment['id'] ?? $ref)),
            'failed', 'voided', 'refunded' => new CheckoutState(CheckoutState::FAILED, $amount,
                failure: CardFailure::fromCode((string) ($source['response_code'] ?? ''), (string) ($source['message'] ?? ''))->value),
            default => new CheckoutState(CheckoutState::OPEN, $amount),
        };
    }

    public function findSaved(string $checkoutId): ?string
    {
        try {
            $response = $this->http()->get('/v1/payments/'.rawurlencode($checkoutId));
        } catch (ConnectionException $e) {
            throw new GatewayException('Moyasar could not be reached: '.$e->getMessage(), previous: $e);
        }
        if ($response->status() === 404) {
            return null;
        }
        if ($response->failed()) {
            throw new GatewayException('Moyasar: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        return isset($response['id']) ? (string) $response['id'] : null;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('billing.payments.moyasar.base'))
            ->withBasicAuth((string) ($this->credentials['secret_key'] ?? ''), '')
            ->asJson()->acceptJson()->timeout((int) config('billing.payments.timeout'));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        $http = $this->http();

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
