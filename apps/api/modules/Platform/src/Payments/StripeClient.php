<?php

namespace Modules\Platform\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\SharedKernel\Health\Faults\StripeFaults;
use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceHealth;
use Modules\SharedKernel\Money\Money;

/**
 * The platform's own small Stripe client (docs/architecture/02, section 11,
 * "الشحن"), on the platform's secret key (Platform settings › Stripe,
 * stripe_platform_secret): the platform is the merchant when an academy buys
 * a pack. Plain HTTP (no SDK; tests fake it). A customer per academy, a
 * hosted Checkout page that may keep the card for later charges with no one
 * present, the session or PaymentIntent read back (only Stripe saying "paid"
 * counts), a charge on the saved card (off_session, an Idempotency-Key, so a
 * repeat never charges twice), and the webhook's signature checked with the
 * platform webhook's own secret (stripe_platform_webhook_secret). How Stripe
 * answers goes to ServiceHealth ('stripe'); a card's own answer is not a fault.
 *
 * Keys and Stripe's references are never logged; errors carry Stripe's code only.
 */
final class StripeClient
{
    private const TOLERANCE_SECONDS = 300;

    /** Whether the platform's key is set: without it, packs are bought by transfer only. */
    public static function configured(): bool
    {
        return filled(config('billing.payments.stripe.platform_secret'));
    }

    /** @param  array<string, string>  $metadata */
    public function createCustomer(string $academyId, string $name, array $metadata): string
    {
        $customer = $this->send(fn (PendingRequest $http) => $http->withHeaders(['Idempotency-Key' => 'platform-customer-'.$academyId])
            ->post('/v1/customers', ['name' => mb_substr($name, 0, 200), 'metadata' => $metadata]));

        return (string) ($customer['id'] ?? '');
    }

    /**
     * A hosted Checkout page for one pack.
     *
     * @param  array<string, string>  $metadata  academy, pack, purchase
     * @return array{id: string, url: string}
     */
    public function createCheckout(string $purchaseId, string $customer, Money $price, string $description, bool $saveCard, string $successUrl, string $cancelUrl, int $expiresAt, array $metadata): array
    {
        $session = $this->send(fn (PendingRequest $http) => $http->withHeaders(['Idempotency-Key' => 'topup-'.$purchaseId])
            ->post('/v1/checkout/sessions', array_filter([
                'mode' => 'payment',
                'customer' => $customer,
                'client_reference_id' => $purchaseId,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'expires_at' => $expiresAt,
                'locale' => 'auto',
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($price->currency),
                        'unit_amount' => $price->minor,
                        'product_data' => ['name' => mb_substr($description, 0, 250)],
                    ],
                ]],
                'metadata' => $metadata,
                'payment_intent_data' => array_filter(['metadata' => $metadata, 'setup_future_usage' => $saveCard ? 'off_session' : null]),
            ], fn ($v) => $v !== null)));

        return ['id' => (string) ($session['id'] ?? ''), 'url' => (string) ($session['url'] ?? '')];
    }

    /** A Checkout Session as Stripe has it now: whether it was paid, and the card when it was kept. */
    public function checkout(string $sessionId): StripePayment
    {
        $session = $this->send(fn (PendingRequest $http) => $http->get('/v1/checkout/sessions/'.rawurlencode($sessionId), ['expand' => ['payment_intent.payment_method']]));
        $intent = is_array($session['payment_intent'] ?? null) ? $session['payment_intent'] : [];
        $status = match (true) {
            ($session['status'] ?? null) === 'complete' && ($session['payment_status'] ?? null) === 'paid' => StripePayment::PAID,
            ($session['status'] ?? null) === 'expired' => StripePayment::EXPIRED,
            default => StripePayment::OPEN,    // still open, or paid by a method that confirms later
        };
        $customer = is_array($session['customer'] ?? null) ? ($session['customer']['id'] ?? null) : ($session['customer'] ?? $intent['customer'] ?? null);

        return new StripePayment(
            $status,
            isset($session['amount_total'], $session['currency']) ? Money::of((int) $session['amount_total'], (string) $session['currency']) : null,
            isset($intent['id']) ? (string) $intent['id'] : (is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : null),
            card: self::card(is_string($customer) ? $customer : null, $intent['payment_method'] ?? null),
        );
    }

    /** A PaymentIntent (a charge on the saved card) as Stripe has it now. */
    public function intent(string $intentId): StripePayment
    {
        $intent = $this->send(fn (PendingRequest $http) => $http->get('/v1/payment_intents/'.rawurlencode($intentId)));
        $error = (array) ($intent['last_payment_error'] ?? []);

        return new StripePayment(
            match ($intent['status'] ?? null) {
                'succeeded' => StripePayment::PAID,
                'requires_action', 'requires_payment_method', 'canceled' => StripePayment::FAILED,
                default => StripePayment::OPEN,
            },
            isset($intent['amount'], $intent['currency']) ? Money::of((int) $intent['amount'], (string) $intent['currency']) : null,
            isset($intent['id']) ? (string) $intent['id'] : $intentId,
            decline: match ($intent['status'] ?? null) {
                'requires_action' => CardDecline::AuthenticationRequired,
                'requires_payment_method', 'canceled' => CardDecline::fromStripe((string) ($error['code'] ?? ''), (string) ($error['decline_code'] ?? '')),
                default => null,
            },
        );
    }

    /**
     * Charges the saved card with no one present. The same key never charges twice (Stripe keeps it a day).
     *
     * @param  array<string, string>  $metadata
     */
    public function chargeSaved(string $idempotencyKey, string $customer, string $paymentMethod, Money $price, string $description, array $metadata): StripeCharge
    {
        try {
            $response = $this->http()->withHeaders(['Idempotency-Key' => $idempotencyKey])->post('/v1/payment_intents', [
                'amount' => $price->minor,
                'currency' => strtolower($price->currency),
                'customer' => $customer,
                'payment_method' => $paymentMethod,
                'off_session' => 'true',
                'confirm' => 'true',
                'description' => mb_substr($description, 0, 250),
                'metadata' => $metadata,
            ]);
        } catch (ConnectionException) {
            ServiceHealth::fault('stripe', ServiceFault::unreachable());

            return StripeCharge::unknown();
        }
        if ($response->serverError() || in_array($response->status(), [409, 429], true)) {
            $this->health($response);

            return StripeCharge::unknown();
        }
        $body = (array) $response->json();
        if ($response->failed()) {
            $error = (array) ($body['error'] ?? []);
            $ref = isset($error['payment_intent']['id']) ? (string) $error['payment_intent']['id'] : null;
            if (($error['type'] ?? null) === 'card_error' || ($error['code'] ?? null) === 'authentication_required') {
                ServiceHealth::ok('stripe');

                return StripeCharge::failed(CardDecline::fromStripe((string) ($error['code'] ?? ''), (string) ($error['decline_code'] ?? '')), $ref);
            }
            $this->health($response);

            return StripeCharge::failed(($error['code'] ?? null) === 'resource_missing' ? CardDecline::InvalidCard : CardDecline::Processing, $ref);
        }
        ServiceHealth::ok('stripe');
        $ref = isset($body['id']) ? (string) $body['id'] : null;

        return match ($body['status'] ?? null) {
            'succeeded' => new StripeCharge(StripeCharge::PAID, $ref),
            'requires_action' => StripeCharge::failed(CardDecline::AuthenticationRequired, $ref),
            'requires_payment_method' => StripeCharge::failed(CardDecline::fromStripe((string) ($body['last_payment_error']['code'] ?? ''), (string) ($body['last_payment_error']['decline_code'] ?? '')), $ref),
            default => new StripeCharge(StripeCharge::PENDING, $ref),    // processing: Stripe tells us later
        };
    }

    /** Lets a removed card go on Stripe's side too; nothing breaks when it was gone already. */
    public function detach(string $paymentMethod): void
    {
        try {
            $this->send(fn (PendingRequest $http) => $http->post('/v1/payment_methods/'.rawurlencode($paymentMethod).'/detach'));
        } catch (StripeException $e) {
            report($e);
        }
    }

    /**
     * A webhook delivery checked against the platform webhook's signing secret
     * (Stripe-Signature, five minutes' tolerance): the event, or null when it is not genuine.
     *
     * @return ?array<string, mixed>
     */
    public static function verified(Request $request, string $secret): ?array
    {
        if ($secret === '') {
            return null;
        }
        $payload = $request->getContent();
        $parts = [];
        foreach (explode(',', (string) $request->header('Stripe-Signature')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key][] = $value;
        }
        $timestamp = (int) ($parts['t'][0] ?? 0);
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $signed = collect($parts['v1'] ?? [])->contains(fn (string $signature) => hash_equals($expected, $signature));
        if (! $signed || abs(time() - $timestamp) > self::TOLERANCE_SECONDS) {
            return null;
        }
        $event = json_decode($payload, true);

        return is_array($event) && isset($event['id'], $event['type']) ? $event : null;
    }

    /** @param  mixed  $method  an expanded payment method */
    private static function card(?string $customer, mixed $method): ?StripeCard
    {
        if (! is_array($method) || ! is_string($customer) || ! str_starts_with($customer, 'cus_') || ! str_starts_with((string) ($method['id'] ?? ''), 'pm_')) {
            return null;
        }
        $card = (array) ($method['card'] ?? []);
        if ($card === []) {
            return null;
        }

        return new StripeCard($customer, (string) $method['id'], mb_substr((string) ($card['brand'] ?? 'card'), 0, 20), mb_substr((string) ($card['last4'] ?? ''), 0, 4),
            (int) ($card['exp_month'] ?? 0), (int) ($card['exp_year'] ?? 0));
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('billing.payments.stripe.base', 'https://api.stripe.com'))
            ->withToken((string) config('billing.payments.stripe.platform_secret'))
            ->asForm()->acceptJson()->timeout((int) config('billing.payments.timeout', 15));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     *
     * @throws StripeException
     */
    private function send(callable $call): array
    {
        if (! self::configured()) {
            throw new StripeException('not_configured');
        }
        try {
            $response = $call($this->http());
        } catch (ConnectionException $e) {
            ServiceHealth::fault('stripe', ServiceFault::unreachable());
            throw new StripeException('unreachable', previous: $e);
        }
        if ($response->failed()) {
            $this->health($response);
            $code = $response->json('error.code') ?? $response->json('error.type');
            throw new StripeException(is_string($code) && $code !== '' ? mb_substr($code, 0, 60) : 'http_'.$response->status());
        }
        ServiceHealth::ok('stripe');

        return (array) $response->json();
    }

    private function health(Response $response): void
    {
        $fault = StripeFaults::of($response->status(), $response->json());
        if ($fault !== null) {
            ServiceHealth::fault('stripe', $fault);
        }
    }
}
