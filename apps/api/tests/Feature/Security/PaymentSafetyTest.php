<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Billing\Actions\Payments\SaveGatewayAccount;
use Modules\Billing\Payments\Gateways\MoyasarGateway;
use Modules\Billing\Payments\Gateways\PaymobGateway;
use Modules\Billing\Payments\Gateways\StripeGateway;
use Modules\Billing\Payments\Gateways\TapGateway;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Http\Idempotent;
use Modules\SharedKernel\Logging\Redactor;

/*
 * Payments and PCI SAQ A (docs/architecture/03, section 11): the card is
 * typed only on the gateway's own page, so no card number reaches the
 * platform; should one ever reach a log line it is masked. Every gateway's
 * webhook is refused unless the gateway signed it, and a webhook only makes
 * the platform ask the gateway. Every write that moves money or lessons can
 * be retried without happening twice.
 */

/** A webhook delivery as the gateway would post it, unsigned. */
function unsignedDelivery(array $body, array $query = []): Request
{
    return Request::create('/webhooks/payments/x/y?'.http_build_query($query), 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode($body));
}

it('refuses every gateway\'s webhook that the gateway did not sign', function () {
    $stripe = new StripeGateway(['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x']);
    $moyasar = new MoyasarGateway(['secret_key' => 'sk_test_x', 'webhook_secret' => 'moyasar-shared']);
    $paymob = new PaymobGateway(['secret_key' => 'x', 'public_key' => 'x', 'api_key' => 'x', 'hmac_secret' => 'paymob-hmac', 'integration_ids' => '1']);
    $tap = new TapGateway(['secret_key' => 'sk_test_tap']);

    expect($stripe->parseWebhook(unsignedDelivery(['id' => 'evt_1', 'type' => 'checkout.session.completed'])))->toBeNull()
        ->and($moyasar->parseWebhook(unsignedDelivery(['id' => 'e1', 'type' => 'payment_paid', 'secret_token' => 'guess'])))->toBeNull()
        ->and($paymob->parseWebhook(unsignedDelivery(['type' => 'TRANSACTION', 'obj' => ['id' => 1, 'success' => true]], ['hmac' => str_repeat('0', 128)])))->toBeNull()
        ->and($tap->parseWebhook(unsignedDelivery(['id' => 'chg_1', 'status' => 'CAPTURED', 'amount' => 10, 'currency' => 'SAR'])))->toBeNull();

    // A gateway account without its webhook secret takes no webhooks at all.
    expect((new MoyasarGateway(['secret_key' => 'sk']))->parseWebhook(unsignedDelivery(['id' => 'e1', 'type' => 'payment_paid', 'secret_token' => ''])))->toBeNull()
        ->and((new TapGateway([]))->parseWebhook(unsignedDelivery(['id' => 'chg_1', 'status' => 'CAPTURED'])))->toBeNull();
});

it('makes every write that moves money or lessons safe to retry (Idempotency-Key)', function () {
    // Paying, proofs, extra lessons, claiming a reward, cancelling or disputing a lesson, time off.
    $moves = '#^api/v1/(family/(invoices|enrollments)/|lessons/\{session\}/(cancel|refuse-substitute|dispute)$|teacher/lessons/\{session\}/dispute$|teacher/time-off$|learning/challenges/claim$)#';
    $found = 0;
    foreach (Route::getRoutes() as $route) {
        if (! preg_match($moves, $route->uri()) || ! array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods())) {
            continue;
        }
        $found++;
        $middleware = $route->gatherMiddleware();
        expect(in_array('idempotent', $middleware, true) || in_array(Idempotent::class, $middleware, true))
            ->toBeTrue("{$route->uri()} moves money or lessons and must be idempotent");
    }
    expect($found)->toBeGreaterThanOrEqual(8);
});

it('masks a card number that would reach a log line, and nothing that only looks like one', function () {
    expect(Redactor::text('paid with 4111 1111 1111 1111 today'))->toBe('paid with [card ****1111] today')
        ->and(Redactor::text('{"pan":"5555555555554444"}'))->toBe('{"pan":"[card ****4444]"}')
        ->and(Redactor::text('amex 3782-822463-10005'))->toBe('amex [card ****0005]')
        // Not a card: fails Luhn, a millisecond timestamp, an invoice number.
        ->and(Redactor::text('ref 4111111111111112'))->toBe('ref 4111111111111112')
        ->and(Redactor::text('at 1760000000000'))->toBe('at 1760000000000')
        ->and(Redactor::text('INV-2026-000123'))->toBe('INV-2026-000123');
});

it('keeps gateway keys encrypted at rest and out of every serialisation', function () {
    $academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $admin = User::factory()->withRole(Role::Admin)->create();
    $this->actingAs($admin);
    $account = app(SaveGatewayAccount::class)->handle(null, [
        'gateway' => 'tap', 'label' => 'Tap', 'mode' => 'test', 'secret_key' => 'sk_test_very_secret_tap_key',
    ], $admin->id);

    expect((string) DB::table('gateway_accounts')->where('id', $account->id)->value('credentials'))->not->toContain('sk_test_very_secret_tap_key')
        ->and(json_encode($account->fresh()->toArray()))->not->toContain('sk_test_very_secret_tap_key')
        ->and($account->fresh()->credentials['secret_key'])->toBe('sk_test_very_secret_tap_key');
    expect($academy->id)->not->toBeEmpty();
});
