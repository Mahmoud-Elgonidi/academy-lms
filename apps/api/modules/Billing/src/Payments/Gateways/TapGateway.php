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
 * Tap (the Gulf): KNET, BENEFIT, mada, cards and Apple Pay on Tap's page
 * (source "src_all"). Amounts go in major units (3 decimals for the dinars).
 * A webhook is only a nudge: the charge is always asked of Tap with the
 * academy's key before anything is recorded.
 */
final class TapGateway implements PaymentGateway
{
    /** Calling codes, longest first, to split a payer's E.164 number the way Tap wants it. */
    private const CALLING_CODES = ['965', '966', '971', '973', '974', '968', '962', '20', '44', '1'];

    /** @param  array<string, string>  $credentials  secret_key */
    public function __construct(private readonly array $credentials) {}

    public function refuses(Money $amount): ?string
    {
        return null;
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $charge = $this->send(fn (PendingRequest $http) => $http->post('/v2/charges', [
            'amount' => (float) $request->amount->decimal(),
            'currency' => $request->amount->currency,
            'customer_initiated' => true,
            'threeDSecure' => true,
            'save_card' => false,
            'description' => $request->description,
            'metadata' => ['udf1' => $request->checkoutId],
            'reference' => ['transaction' => $request->checkoutId, 'order' => $request->invoiceNumber],
            'receipt' => ['email' => false, 'sms' => false],
            'customer' => array_filter([
                'first_name' => $request->payerName ?: 'Customer',
                'email' => $request->payerEmail,
                'phone' => $request->payerEmail ? null : $this->phone($request->payerPhone),
            ]),
            'source' => ['id' => 'src_all'],
            'post' => ['url' => $request->webhookUrl],
            'redirect' => ['url' => $request->returnUrl],
        ]));

        return new CheckoutSession((string) $charge['id'], (string) ($charge['transaction']['url'] ?? ''));
    }

    public function fetch(string $providerRef): CheckoutState
    {
        $charge = $this->send(fn (PendingRequest $http) => $http->get('/v2/charges/'.rawurlencode($providerRef)));
        $amount = isset($charge['amount'], $charge['currency']) ? Money::parse((string) $charge['amount'], (string) $charge['currency']) : null;

        return match ($charge['status'] ?? null) {
            'CAPTURED' => new CheckoutState(CheckoutState::PAID, $amount, $charge['reference']['payment'] ?? $providerRef),
            'INITIATED', 'IN_PROGRESS' => new CheckoutState(CheckoutState::OPEN, $amount),
            default => new CheckoutState(CheckoutState::FAILED, $amount, failure: (string) ($charge['response']['message'] ?? $charge['status'] ?? 'failed')),
        };
    }

    public function parseWebhook(Request $request): ?GatewayEvent
    {
        $charge = $request->json()->all();
        $id = (string) ($charge['id'] ?? '');
        if (! str_starts_with($id, 'chg_')) {
            return null;
        }

        return new GatewayEvent($id.':'.($charge['status'] ?? ''), 'charge.'.strtolower((string) ($charge['status'] ?? 'updated')), $id, $charge);
    }

    public function check(): ?string
    {
        try {
            $this->send(fn (PendingRequest $http) => $http->post('/v2/charges/list', ['limit' => 1]));

            return null;
        } catch (GatewayException $e) {
            return $e->getMessage();
        }
    }

    /** @return ?array{country_code: string, number: string} */
    private function phone(?string $e164): ?array
    {
        $digits = ltrim((string) $e164, '+');
        foreach (self::CALLING_CODES as $code) {
            if ($digits !== '' && str_starts_with($digits, $code)) {
                return ['country_code' => $code, 'number' => substr($digits, strlen($code))];
            }
        }

        return null;
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        $http = Http::baseUrl((string) config('billing.payments.tap.base'))
            ->withToken((string) ($this->credentials['secret_key'] ?? ''))
            ->asJson()->acceptJson()->timeout((int) config('billing.payments.timeout'));

        try {
            $response = $call($http);
        } catch (ConnectionException $e) {
            throw new GatewayException('Tap could not be reached: '.$e->getMessage(), previous: $e);
        }
        if ($response->failed()) {
            throw new GatewayException('Tap: '.($response->json('errors.0.description') ?? $response->json('message') ?? 'HTTP '.$response->status()));
        }

        return (array) $response->json();
    }
}
