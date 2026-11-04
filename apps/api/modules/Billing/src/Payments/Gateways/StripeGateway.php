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
use Modules\SharedKernel\Health\Faults\StripeFaults;
use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceHealth;
use Modules\SharedKernel\Money\Money;

/**
 * Stripe Checkout (hosted page): cards, Apple Pay and Google Pay, in most
 * currencies. On the academy's own secret key, or (Stripe Connect Standard)
 * the platform's key acting for the academy's connected account: a direct
 * charge on that account (Stripe-Account), with the platform's fee if one
 * is set. Webhooks are signed with the endpoint's secret (Stripe-Signature);
 * for connected accounts that is the platform's Connect endpoint. On the
 * platform's key, how Stripe answers goes to ServiceHealth ('stripe'); an
 * academy's own key, or a connected account that left, is the academy's.
 *
 * Saved cards (J5b): the Checkout Session makes a Stripe customer and asks to
 * keep the card for charges with no one present (payment_intent_data
 * setup_future_usage off_session); the session's PaymentIntent, its payment
 * method expanded, gives the customer, the payment method and the card's
 * brand, last four and expiry. A renewal is a PaymentIntent confirmed
 * off_session (Idempotency-Key "saved-{checkout}"); a decline comes back as a
 * 402 card_error with its code and decline_code; "authentication_required"
 * means the bank wants the payer: never pushed, the pay link goes instead.
 */
final class StripeGateway implements PaymentGateway
{
    /** Three-decimal currencies: Stripe charges them in steps of 10 of the minor unit. */
    private const STEP_OF_TEN = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];

    private const TOLERANCE_SECONDS = 300;

    /** @param  array<string, mixed>  $credentials  secret_key, webhook_secret; or (Connect) account_id, application_fee_bp, academy_id */
    public function __construct(private readonly array $credentials) {}

    public function refuses(Money $amount): ?string
    {
        return in_array($amount->currency, self::STEP_OF_TEN, true) && $amount->minor % 10 !== 0
            ? __('billing::payments.errors.stripe_step', ['currency' => $amount->currency])
            : null;
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $metadata = array_filter(['checkout_id' => $request->checkoutId, 'invoice' => $request->invoiceNumber, 'academy' => $this->credentials['academy_id'] ?? null]);
        $fee = ! empty($this->credentials['account_id']) ? intdiv($request->amount->minor * (int) ($this->credentials['application_fee_bp'] ?? 0), 10_000) : 0;
        $session = $this->send(fn (PendingRequest $http) => $http->withHeaders(['Idempotency-Key' => 'checkout-'.$request->checkoutId.($request->saveCard ? '-save' : '')])
            ->post('/v1/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => $request->checkoutId,
                'success_url' => $request->returnUrl,
                'cancel_url' => $request->cancelUrl,
                'expires_at' => $request->expiresAt->getTimestamp(),
                'locale' => 'auto',
                'customer_email' => $request->payerEmail,
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($request->amount->currency),
                        'unit_amount' => $request->amount->minor,
                        'product_data' => ['name' => $request->description],
                    ],
                ]],
                'metadata' => $metadata,
                'payment_intent_data' => array_filter(['metadata' => $metadata, 'application_fee_amount' => $fee > 0 ? $fee : null,
                    'setup_future_usage' => $request->saveCard ? 'off_session' : null]),
                ...($request->saveCard ? ['customer_creation' => 'always'] : []),
            ]));

        return new CheckoutSession((string) $session['id'], (string) $session['url']);
    }

    public function fetch(string $providerRef): CheckoutState
    {
        $session = $this->send(fn (PendingRequest $http) => $http->get('/v1/checkout/sessions/'.rawurlencode($providerRef)));
        $amount = isset($session['amount_total'], $session['currency']) ? Money::of((int) $session['amount_total'], (string) $session['currency']) : null;

        return match (true) {
            ($session['status'] ?? null) === 'complete' && in_array($session['payment_status'] ?? null, ['paid', 'no_payment_required'], true) => new CheckoutState(CheckoutState::PAID, $amount, $session['payment_intent'] ?? $providerRef),
            ($session['status'] ?? null) === 'expired' => new CheckoutState(CheckoutState::EXPIRED, $amount),
            default => new CheckoutState(CheckoutState::OPEN, $amount),   // still open, or paid by a method that confirms later
        };
    }

    public function parseWebhook(Request $request): ?GatewayEvent
    {
        $event = self::verified($request, (string) ($this->credentials['webhook_secret'] ?? ''));
        if ($event === null) {
            return null;
        }
        // A connected account's event must be about that account.
        if (! empty($this->credentials['account_id']) && ($event['account'] ?? null) !== $this->credentials['account_id']) {
            return null;
        }
        $object = $event['data']['object'] ?? [];

        // A checkout page's event names its session; a saved card's charge (J5b) its PaymentIntent.
        $ref = match (true) {
            str_starts_with((string) $event['type'], 'checkout.session.') => $object['id'] ?? null,
            str_starts_with((string) $event['type'], 'payment_intent.') => $object['id'] ?? null,
            default => null,
        };

        return new GatewayEvent((string) $event['id'], (string) $event['type'], $ref, $event);
    }

    /**
     * A Stripe webhook delivery checked against a signing secret (Stripe-Signature,
     * five minutes' tolerance): the event, or null when it is not genuine.
     *
     * @return ?array<string, mixed>
     */
    public static function verified(Request $request, string $secret): ?array
    {
        $payload = $request->getContent();
        $parts = [];
        foreach (explode(',', (string) $request->header('Stripe-Signature')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key][] = $value;
        }
        $timestamp = (int) ($parts['t'][0] ?? 0);
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $signed = $secret !== '' && collect($parts['v1'] ?? [])->contains(fn (string $signature) => hash_equals($expected, $signature));
        if (! $signed || abs(time() - $timestamp) > self::TOLERANCE_SECONDS) {
            return null;
        }
        $event = json_decode($payload, true);

        return is_array($event) && isset($event['id'], $event['type']) ? $event : null;
    }

    public function check(): ?string
    {
        try {
            $this->send(fn (PendingRequest $http) => $http->get('/v1/balance'));

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
        $session = $this->send(fn (PendingRequest $http) => $http->get('/v1/checkout/sessions/'.rawurlencode($providerRef), ['expand' => ['payment_intent.payment_method']]));
        $intent = is_array($session['payment_intent'] ?? null) ? $session['payment_intent'] : [];
        $method = is_array($intent['payment_method'] ?? null) ? $intent['payment_method'] : [];
        $customer = (string) (is_array($session['customer'] ?? null) ? ($session['customer']['id'] ?? '') : ($session['customer'] ?? $intent['customer'] ?? ''));
        $card = (array) ($method['card'] ?? []);
        if (! str_starts_with($customer, 'cus_') || ! str_starts_with((string) ($method['id'] ?? ''), 'pm_') || $card === []) {
            return null;
        }

        return new SavedCard(['customer' => $customer, 'payment_method' => (string) $method['id']],
            (string) ($card['brand'] ?? 'card'), (string) ($card['last4'] ?? ''), (int) ($card['exp_month'] ?? 0), (int) ($card['exp_year'] ?? 0));
    }

    public function chargeSaved(SavedCharge $charge): ChargeResult
    {
        $metadata = array_filter(['checkout_id' => $charge->checkoutId, 'invoice' => $charge->invoiceNumber, 'academy' => $this->credentials['academy_id'] ?? null]);
        $fee = ! empty($this->credentials['account_id']) ? intdiv($charge->amount->minor * (int) ($this->credentials['application_fee_bp'] ?? 0), 10_000) : 0;
        try {
            $response = $this->http()->withHeaders(['Idempotency-Key' => 'saved-'.$charge->checkoutId])->post('/v1/payment_intents', array_filter([
                'amount' => $charge->amount->minor,
                'currency' => strtolower($charge->amount->currency),
                'customer' => (string) ($charge->tokens['customer'] ?? ''),
                'payment_method' => (string) ($charge->tokens['payment_method'] ?? ''),
                'off_session' => 'true',
                'confirm' => 'true',
                'description' => $charge->description,
                'metadata' => $metadata,
                'application_fee_amount' => $fee > 0 ? $fee : null,
            ], fn ($v) => $v !== null));
        } catch (ConnectionException) {
            return ChargeResult::unknown();
        }
        if ($response->serverError() || $response->status() === 429 || $response->status() === 409) {
            return ChargeResult::unknown();
        }
        $body = (array) $response->json();
        if ($response->failed()) {
            $error = (array) ($body['error'] ?? []);
            $ref = isset($error['payment_intent']['id']) ? (string) $error['payment_intent']['id'] : null;
            if (($error['type'] ?? null) !== 'card_error' && ($error['code'] ?? null) !== 'authentication_required') {
                return ChargeResult::failed(($error['code'] ?? null) === 'resource_missing' ? CardFailure::InvalidCard : CardFailure::Processing, $ref);
            }

            return ChargeResult::failed(CardFailure::fromCode((string) (($error['code'] ?? null) === 'card_declined' ? ($error['decline_code'] ?? 'card_declined') : ($error['code'] ?? ''))), $ref);
        }
        $ref = isset($body['id']) ? (string) $body['id'] : null;

        return match ($body['status'] ?? null) {
            'succeeded' => new ChargeResult(ChargeResult::PAID, $ref),
            'requires_action' => ChargeResult::failed(CardFailure::AuthenticationRequired, $ref),
            'requires_payment_method' => ChargeResult::failed(CardFailure::fromCode((string) ($body['last_payment_error']['decline_code'] ?? $body['last_payment_error']['code'] ?? '')), $ref),
            default => new ChargeResult(ChargeResult::PENDING, $ref),      // processing: Stripe tells us (the webhook, the sweeper)
        };
    }

    public function fetchSaved(string $ref): CheckoutState
    {
        $intent = $this->send(fn (PendingRequest $http) => $http->get('/v1/payment_intents/'.rawurlencode($ref)));
        $amount = isset($intent['amount'], $intent['currency']) ? Money::of((int) $intent['amount'], (string) $intent['currency']) : null;
        $error = (array) ($intent['last_payment_error'] ?? []);

        return match ($intent['status'] ?? null) {
            'succeeded' => new CheckoutState(CheckoutState::PAID, $amount, (string) ($intent['id'] ?? $ref)),
            'requires_action' => new CheckoutState(CheckoutState::FAILED, $amount, failure: CardFailure::AuthenticationRequired->value),
            'requires_payment_method', 'canceled' => new CheckoutState(CheckoutState::FAILED, $amount,
                failure: CardFailure::fromCode((string) ($error['decline_code'] ?? $error['code'] ?? ''))->value),
            default => new CheckoutState(CheckoutState::OPEN, $amount),
        };
    }

    public function findSaved(string $checkoutId): ?string
    {
        $found = $this->send(fn (PendingRequest $http) => $http->get('/v1/payment_intents/search', ['query' => "metadata['checkout_id']:'{$checkoutId}'", 'limit' => 1]));
        $id = (string) ($found['data'][0]['id'] ?? '');

        return str_starts_with($id, 'pi_') ? $id : null;
    }

    private function http(): PendingRequest
    {
        $http = Http::baseUrl((string) config('billing.payments.stripe.base'))
            ->withToken((string) ($this->credentials['secret_key'] ?? config('billing.payments.stripe.platform_secret')))
            ->asForm()->acceptJson()->timeout((int) config('billing.payments.timeout'));

        return ! empty($this->credentials['account_id']) ? $http->withHeaders(['Stripe-Account' => $this->credentials['account_id']]) : $http;
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        $http = $this->http();

        $platform = empty($this->credentials['secret_key']);
        try {
            $response = $call($http);
        } catch (ConnectionException $e) {
            if ($platform) {
                ServiceHealth::fault('stripe', ServiceFault::unreachable());
            }
            throw new GatewayException('Stripe could not be reached: '.$e->getMessage(), previous: $e);
        }
        if ($response->failed()) {
            if ($platform && ($fault = StripeFaults::of($response->status(), $response->json(), ! empty($this->credentials['account_id']))) !== null) {
                ServiceHealth::fault('stripe', $fault);
            }
            throw new GatewayException('Stripe: '.($response->json('error.message') ?? 'HTTP '.$response->status()));
        }
        if ($platform) {
            ServiceHealth::ok('stripe');
        }

        return (array) $response->json();
    }
}
