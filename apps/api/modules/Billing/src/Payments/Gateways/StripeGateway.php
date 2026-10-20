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
        $session = $this->send(fn (PendingRequest $http) => $http->withHeaders(['Idempotency-Key' => 'checkout-'.$request->checkoutId])
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
                'payment_intent_data' => array_filter(['metadata' => $metadata, 'application_fee_amount' => $fee > 0 ? $fee : null]),
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

        return new GatewayEvent((string) $event['id'], (string) $event['type'],
            str_starts_with((string) $event['type'], 'checkout.session.') ? ($object['id'] ?? null) : null, $event);
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

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        $http = Http::baseUrl((string) config('billing.payments.stripe.base'))
            ->withToken((string) ($this->credentials['secret_key'] ?? config('billing.payments.stripe.platform_secret')))
            ->asForm()->acceptJson()->timeout((int) config('billing.payments.timeout'));
        if (! empty($this->credentials['account_id'])) {
            $http = $http->withHeaders(['Stripe-Account' => $this->credentials['account_id']]);
        }

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
