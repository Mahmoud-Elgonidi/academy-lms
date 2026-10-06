<?php

namespace Modules\Billing\Payments\Gateways;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Modules\Billing\Payments\CheckoutRequest;
use Modules\Billing\Payments\CheckoutSession;
use Modules\Billing\Payments\CheckoutState;
use Modules\Billing\Payments\GatewayEvent;
use Modules\Billing\Payments\PaymentGateway;
use Modules\SharedKernel\Money\Money;

/**
 * A pretend gateway with a page of its own in the app ("pay" or "decline"),
 * to try the whole flow without keys: local, the demo, tests. Never in
 * production (billing.payments.demo_gateway). It keeps its state in the
 * cache, as a real gateway keeps it on its side.
 */
final class DemoGateway implements PaymentGateway
{
    public function refuses(Money $amount): ?string
    {
        return null;
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $ref = 'demo_'.$request->checkoutId;
        Cache::put(self::key($ref), [
            'status' => CheckoutState::OPEN, 'minor' => $request->amount->minor, 'currency' => $request->amount->currency,
            'description' => $request->description, 'return' => $request->returnUrl,
        ], $request->expiresAt->addDay());

        return new CheckoutSession($ref, url(URL::temporarySignedRoute('billing.demo-gateway', $request->expiresAt, ['ref' => $ref], absolute: false)));
    }

    public function fetch(string $providerRef): CheckoutState
    {
        $state = self::state($providerRef);
        if ($state === null) {
            return new CheckoutState(CheckoutState::EXPIRED);
        }

        return new CheckoutState($state['status'], Money::of($state['minor'], $state['currency']),
            $state['status'] === CheckoutState::PAID ? 'demo_pay_'.substr($providerRef, 5, 8) : null,
            $state['status'] === CheckoutState::FAILED ? 'declined' : null);
    }

    public function parseWebhook(Request $request): ?GatewayEvent
    {
        return null;   // its page settles the checkout itself
    }

    public function check(): ?string
    {
        return null;
    }

    /** @return ?array{status: string, minor: int, currency: string, description: string, return: string} */
    public static function state(string $ref): ?array
    {
        return Cache::get(self::key($ref));
    }

    /** The payer pressed "pay" or "decline" on the pretend page. */
    public static function decide(string $ref, bool $pay): ?string
    {
        $state = self::state($ref);
        if ($state === null || $state['status'] !== CheckoutState::OPEN) {
            return $state['return'] ?? null;
        }
        Cache::put(self::key($ref), ['status' => $pay ? CheckoutState::PAID : CheckoutState::FAILED] + $state, now()->addDay());

        return $state['return'];
    }

    private static function key(string $ref): string
    {
        return 'billing:demo-gateway:'.$ref;
    }
}
