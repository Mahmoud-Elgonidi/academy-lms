<?php

namespace Modules\Billing\Payments\Gateways;

use Carbon\CarbonImmutable;
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
 * Paymob (Egypt): cards and mobile wallets on Paymob's Unified Checkout.
 * An intention (secret key) opens the page for the academy's integrations;
 * its Paymob order is our reference. The processed callback is signed with
 * HMAC-SHA512 over twenty fields of the transaction in a fixed order (the
 * academy's HMAC secret); even so, the transaction is always asked of
 * Paymob (transaction inquiry, with a token from the API key) before
 * anything is recorded.
 */
final class PaymobGateway implements PaymentGateway
{
    /** The callback fields Paymob signs, in its order. */
    private const SIGNED = [
        'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure',
        'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment', 'is_voided', 'order.id', 'owner', 'pending',
        'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success',
    ];

    /** @param  array<string, string>  $credentials  secret_key, public_key, api_key, hmac_secret, integration_ids */
    public function __construct(private readonly array $credentials) {}

    public function refuses(Money $amount): ?string
    {
        return null;   // the account's currencies (EGP) are checked before
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        [$first, $last] = self::names($request->payerName);
        $intention = $this->send(fn (PendingRequest $http) => $http->withHeaders(['Authorization' => 'Token '.($this->credentials['secret_key'] ?? '')])
            ->post('/v1/intention/', [
                'amount' => $request->amount->minor,
                'currency' => $request->amount->currency,
                'payment_methods' => $this->integrations(),
                'items' => [['name' => mb_substr($request->description, 0, 50), 'amount' => $request->amount->minor, 'description' => $request->invoiceNumber, 'quantity' => 1]],
                'billing_data' => [
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $request->payerEmail ?: 'NA',
                    'phone_number' => $request->payerPhone ?: 'NA',
                ],
                'special_reference' => $request->checkoutId,
                'notification_url' => $request->webhookUrl,
                'redirection_url' => $request->returnUrl,
                'expiration' => max(60, CarbonImmutable::now()->diffInSeconds($request->expiresAt, absolute: true)),
            ]));

        $order = $intention['intention_order_id'] ?? null;
        $secret = $intention['client_secret'] ?? null;
        if (! $order || ! $secret) {
            throw new GatewayException('Paymob: the intention came back without an order or a client secret.');
        }

        return new CheckoutSession((string) $order, rtrim((string) config('billing.payments.paymob.base'), '/').'/unifiedcheckout/?'.http_build_query([
            'publicKey' => $this->credentials['public_key'] ?? '',
            'clientSecret' => $secret,
        ]));
    }

    public function fetch(string $providerRef): CheckoutState
    {
        $token = $this->token();
        $response = $this->call(fn (PendingRequest $http) => $http->withToken($token)->post('/api/ecommerce/orders/transaction_inquiry', ['order_id' => (int) $providerRef]));
        if ($response->status() === 404) {
            return new CheckoutState(CheckoutState::OPEN);   // no transaction on the order yet
        }
        $txn = $this->json($response);
        $amount = isset($txn['amount_cents'], $txn['currency']) ? Money::of((int) $txn['amount_cents'], (string) $txn['currency']) : null;

        return match (true) {
            self::yes($txn['pending'] ?? false) => new CheckoutState(CheckoutState::OPEN, $amount),
            self::yes($txn['success'] ?? false) && ! self::yes($txn['is_voided'] ?? false) && ! self::yes($txn['is_refunded'] ?? false) => new CheckoutState(CheckoutState::PAID, $amount, isset($txn['id']) ? (string) $txn['id'] : $providerRef),
            default => new CheckoutState(CheckoutState::FAILED, $amount, failure: (string) ($txn['data']['message'] ?? 'declined')),
        };
    }

    public function parseWebhook(Request $request): ?GatewayEvent
    {
        $secret = (string) ($this->credentials['hmac_secret'] ?? '');
        $body = $request->json()->all();
        $txn = $body['obj'] ?? null;
        if ($secret === '' || ! is_array($txn) || ($body['type'] ?? 'TRANSACTION') !== 'TRANSACTION' || ! isset($txn['id'])) {
            return null;
        }
        $expected = hash_hmac('sha512', self::signedString($txn), $secret);
        if (! hash_equals($expected, strtolower((string) $request->query('hmac')))) {
            return null;
        }

        return new GatewayEvent('txn:'.$txn['id'].':'.(self::yes($txn['pending'] ?? false) ? 'pending' : (self::yes($txn['success'] ?? false) ? 'success' : 'failed')),
            'transaction.'.(self::yes($txn['success'] ?? false) ? 'success' : 'failed'),
            isset($txn['order']['id']) ? (string) $txn['order']['id'] : null, $body);
    }

    public function check(): ?string
    {
        try {
            $this->token();

            return $this->integrations() === [] ? __('billing::payments.errors.paymob_integrations') : null;
        } catch (GatewayException $e) {
            return $e->getMessage();
        }
    }

    /** The string Paymob signs: the twenty fields' values, booleans as "true"/"false", one after another. */
    public static function signedString(array $txn): string
    {
        return implode('', array_map(function (string $field) use ($txn) {
            $value = data_get($txn, $field);

            return is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }, self::SIGNED));
    }

    /** @return list<int> the academy's integration ids (a card one, a wallet one, …) */
    private function integrations(): array
    {
        return array_values(array_map('intval', array_filter(preg_split('/[\s,]+/', (string) ($this->credentials['integration_ids'] ?? '')) ?: [], fn ($id) => ctype_digit($id))));
    }

    private function token(): string
    {
        $auth = $this->send(fn (PendingRequest $http) => $http->post('/api/auth/tokens', ['api_key' => $this->credentials['api_key'] ?? '']));
        if (empty($auth['token'])) {
            throw new GatewayException('Paymob: no token for this API key.');
        }

        return (string) $auth['token'];
    }

    /** @return array{0: string, 1: string} */
    private static function names(?string $name): array
    {
        $parts = preg_split('/\s+/u', trim((string) $name)) ?: [];
        $first = array_shift($parts) ?: 'Customer';

        return [$first, $parts !== [] ? implode(' ', $parts) : $first];
    }

    private static function yes(mixed $value): bool
    {
        return $value === true || $value === 'true' || $value === 1 || $value === '1';
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        return $this->json($this->call($call));
    }

    /** @param  callable(PendingRequest): Response  $call */
    private function call(callable $call): Response
    {
        $http = Http::baseUrl((string) config('billing.payments.paymob.base'))->asJson()->acceptJson()->timeout((int) config('billing.payments.timeout'));
        try {
            return $call($http);
        } catch (ConnectionException $e) {
            throw new GatewayException('Paymob could not be reached: '.$e->getMessage(), previous: $e);
        }
    }

    /** @return array<string, mixed> */
    private function json(Response $response): array
    {
        if ($response->failed()) {
            $detail = $response->json('detail') ?? $response->json('message') ?? (is_array($response->json()) ? json_encode($response->json(), JSON_UNESCAPED_UNICODE) : null);
            throw new GatewayException('Paymob: '.(is_string($detail) ? mb_substr($detail, 0, 200) : 'HTTP '.$response->status()));
        }

        return (array) $response->json();
    }
}
