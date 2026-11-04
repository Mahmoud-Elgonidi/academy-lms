<?php

namespace Modules\Billing\Payments\Gateways;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Modules\Billing\Payments\Cards\CardFailure;
use Modules\Billing\Payments\Cards\ChargeResult;
use Modules\Billing\Payments\Cards\SavedCard;
use Modules\Billing\Payments\Cards\SavedCharge;
use Modules\Billing\Payments\CheckoutRequest;
use Modules\Billing\Payments\CheckoutSession;
use Modules\Billing\Payments\CheckoutState;
use Modules\Billing\Payments\GatewayEvent;
use Modules\Billing\Payments\PayLinks;
use Modules\Billing\Payments\PaymentGateway;
use Modules\SharedKernel\Money\Money;

/**
 * A pretend gateway with a page of its own in the app ("pay" or "decline"),
 * to try the whole flow without keys: local, the demo, tests. Never in
 * production (billing.payments.demo_gateway). It keeps its state in the
 * cache, as a real gateway keeps it on its side. Asked to save the card, its
 * page keeps a pretend one (Visa ****4242, 12/2034) with a random token; a
 * saved card is charged at once and paid, unless a test or the e2e set the
 * next charge's outcome (nextCharge).
 */
final class DemoGateway implements PaymentGateway
{
    public function __construct(private readonly PayLinks $links) {}

    public function refuses(Money $amount): ?string
    {
        return null;
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        $ref = 'demo_'.$request->checkoutId;
        Cache::put(self::key($ref), [
            'status' => CheckoutState::OPEN, 'minor' => $request->amount->minor, 'currency' => $request->amount->currency,
            'description' => $request->description, 'return' => $request->returnUrl, 'save' => $request->saveCard,
        ], $request->expiresAt->addDay());

        // Its page is on the academy's host (a checkout begun through the API's shared host too); its forms post back there.
        return new CheckoutSession($ref, $this->links->forBrowser(URL::temporarySignedRoute('billing.demo-gateway', $request->expiresAt, ['ref' => $ref], absolute: false)));
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

    public function savesCards(): bool
    {
        return true;
    }

    public function savedCard(string $providerRef): ?SavedCard
    {
        $card = self::state($providerRef)['card'] ?? null;

        return is_array($card) ? new SavedCard(['token' => (string) $card['token']], 'visa', '4242', 12, 2034) : null;
    }

    public function chargeSaved(SavedCharge $charge): ChargeResult
    {
        if (! str_starts_with((string) ($charge->tokens['token'] ?? ''), 'demo_tok_')) {
            return ChargeResult::failed(CardFailure::InvalidCard);
        }
        $outcome = Cache::pull(self::NEXT_CHARGE) ?? ChargeResult::PAID;
        $ref = 'demo_chg_'.$charge->checkoutId;
        if ($outcome === 'network') {
            return ChargeResult::unknown();      // as if the answer never came back (nothing was kept: nothing was charged)
        }
        $failure = CardFailure::tryFrom((string) $outcome);
        Cache::put(self::key($ref), [
            'status' => $failure === null ? CheckoutState::PAID : CheckoutState::FAILED, 'minor' => $charge->amount->minor,
            'currency' => $charge->amount->currency, 'description' => $charge->description, 'return' => $charge->returnUrl,
            'failure' => $failure?->value,
        ], now()->addDays(3));

        return $failure === null ? new ChargeResult(ChargeResult::PAID, $ref) : ChargeResult::failed($failure, $ref);
    }

    public function fetchSaved(string $ref): CheckoutState
    {
        $state = self::state($ref);
        if ($state === null) {
            return new CheckoutState(CheckoutState::EXPIRED);
        }

        return new CheckoutState($state['status'], Money::of($state['minor'], $state['currency']),
            $state['status'] === CheckoutState::PAID ? $ref : null, $state['failure'] ?? null);
    }

    public function findSaved(string $checkoutId): ?string
    {
        return self::state('demo_chg_'.$checkoutId) !== null ? 'demo_chg_'.$checkoutId : null;
    }

    /** The outcome of the next charge on a saved card: a CardFailure's value, "network" (no answer), or null to pay (tests, the e2e). */
    public static function nextCharge(?string $outcome): void
    {
        $outcome === null ? Cache::forget(self::NEXT_CHARGE) : Cache::forever(self::NEXT_CHARGE, $outcome);
    }

    private const NEXT_CHARGE = 'billing:demo-gateway:next-charge';

    /** @return ?array{status: string, minor: int, currency: string, description: string, return: string, save?: bool, card?: array{token: string}, failure?: ?string} */
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
        // Asked to save it, the pretend page keeps a pretend card (its token, as a real gateway's).
        $card = $pay && ! empty($state['save']) ? ['card' => ['token' => 'demo_tok_'.bin2hex(random_bytes(8))]] : [];
        Cache::put(self::key($ref), ['status' => $pay ? CheckoutState::PAID : CheckoutState::FAILED] + $card + $state, now()->addDay());

        return $state['return'];
    }

    private static function key(string $ref): string
    {
        return 'billing:demo-gateway:'.$ref;
    }
}
