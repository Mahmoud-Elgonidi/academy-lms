<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Billing\Payments\GatewayException;
use Modules\Billing\Payments\Gateways\StripeGateway;
use Modules\Billing\Payments\StripeConnect;
use Modules\Classroom\Models\Room;
use Modules\Classroom\Video\LiveKit\LiveKitProvider;
use Modules\Communication\Channels\Push\FcmPushGateway;
use Modules\Communication\Channels\Sms\TwilioSmsGateway;
use Modules\Communication\Channels\WhatsApp\MetaCloudWhatsAppGateway;
use Modules\SharedKernel\Health\ServiceHealth;

/*
 * Each outside service's gateway says how the service answered in real use
 * (ServiceHealth, docs/architecture/03, section 12): a refused key or a
 * payment that did not go through is written down in our words with the
 * provider's code; an answer about one recipient is not; the gateway still
 * fails as before.
 */

beforeEach(function () {
    foreach (['whatsapp', 'twilio', 'fcm', 'livekit', 'stripe'] as $service) {
        ServiceHealth::forget($service);
    }
});

/** One fake for the test; the answer it gives is changed with answer(). */
function answering(string $pattern): void
{
    test()->answer = fn () => Http::response([], 200);
    Http::fake([$pattern => fn ($request) => (test()->answer)($request)]);
}

function answer(Closure|array $response, int $status = 200): void
{
    test()->answer = $response instanceof Closure ? $response : fn () => Http::response($response, $status);
}

function whatsapp(): MetaCloudWhatsAppGateway
{
    return new MetaCloudWhatsAppGateway(['token' => 'EAAG-token', 'phone_number_id' => '1234567', 'api_version' => 'v21.0', 'template' => 'academy_notification', 'otp_template' => 'sign_in_code']);
}

it('notes WhatsApp\'s answers: a payment issue, a missing template by name, never one recipient', function () {
    Http::fake(['graph.facebook.com/*' => Http::sequence()
        ->push(['error' => ['code' => 131026, 'message' => 'Message undeliverable']], 400)
        ->push(['error' => ['code' => 131042, 'message' => 'Business eligibility payment issue']], 400)
        ->push(['error' => ['code' => 132001, 'message' => 'Template name does not exist in the translation']], 404)
        ->push(['messages' => [['id' => 'wamid.1']]])
        ->push(['messages' => [['id' => 'wamid.2']]])]);
    $gateway = whatsapp();

    expect(fn () => $gateway->send('+201000000001', 'T', 'B', 'ar'))->toThrow(RuntimeException::class);
    expect(ServiceHealth::last('whatsapp'))->toBe([]);

    expect(fn () => $gateway->send('+201000000001', 'T', 'B', 'ar'))->toThrow(RuntimeException::class);
    expect(ServiceHealth::last('whatsapp'))->toMatchArray(['problem' => 'quota', 'code' => '131042']);

    expect(fn () => $gateway->sendCode('+201000000001', '123456', 'ar'))->toThrow(RuntimeException::class);
    expect(ServiceHealth::last('whatsapp'))->toMatchArray(['problem' => 'config', 'code' => '132001', 'scope' => 'sign_in_code']);

    // The notifications' template works; the codes' is still missing.
    $gateway->send('+201000000001', 'T', 'B', 'ar');
    expect(ServiceHealth::problem('whatsapp')?->value)->toBe('config');
    $gateway->sendCode('+201000000001', '123456', 'ar');
    expect(ServiceHealth::failing('whatsapp'))->toBeFalse();
});

it('notes Twilio\'s answers, and when it could not be reached', function () {
    $twilio = new TwilioSmsGateway(['sid' => 'AC'.str_repeat('a', 32), 'token' => 't', 'from' => '+15550000000', 'messaging_service_sid' => null]);

    answering('api.twilio.com/*');
    answer(['code' => 20003, 'message' => 'Authenticate', 'status' => 401], 401);
    expect(fn () => $twilio->send('+201000000001', 'code 1'))->toThrow(RuntimeException::class);
    expect(ServiceHealth::last('twilio'))->toMatchArray(['problem' => 'key', 'code' => '20003']);

    answer(fn () => throw new ConnectionException('Could not resolve host'));
    expect(fn () => $twilio->send('+201000000001', 'code 1'))->toThrow(ConnectionException::class);
    expect(ServiceHealth::last('twilio'))->toMatchArray(['problem' => 'unreachable', 'in_a_row' => 2]);
});

it('keeps a phone whose push failed for the project, and notes the service account refused', function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    $account = ['type' => 'service_account', 'project_id' => 'p1', 'private_key_id' => 'k-'.uniqid(), 'private_key' => $pem, 'client_email' => 'push@p1.iam.gserviceaccount.com'];
    $fcm = new FcmPushGateway(['project_id' => 'wrong-project', 'credentials' => null, 'credentials_json' => json_encode($account)]);

    answering('*');
    $gone = fn ($request) => Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404);
    answer(fn ($request) => match (true) {
        str_contains($request->url(), 'oauth2') => Http::response(['access_token' => 'ya29.token']),
        $request['message']['token'] === 'gone-phone' => $gone($request),
        default => Http::response(['error' => ['status' => 'NOT_FOUND', 'message' => 'Requested entity was not found.']], 404),
    });
    expect(fn () => $fcm->send(['live-phone', 'gone-phone'], 'T', 'B', []))->toThrow(RuntimeException::class);
    expect(ServiceHealth::last('fcm'))->toMatchArray(['problem' => 'config']);

    // A device that is gone is only that device.
    answer(fn ($request) => match (true) {
        str_contains($request->url(), 'oauth2') => Http::response(['access_token' => 'ya29.token']),
        $request['message']['token'] === 'gone-phone' => $gone($request),
        default => Http::response(['name' => 'projects/p1/messages/1']),
    });
    $result = $fcm->send(['live-phone', 'gone-phone'], 'T', 'B', []);
    expect($result->deadTokens)->toBe(['gone-phone'])->and(ServiceHealth::failing('fcm'))->toBeFalse();

    Cache::flush();
    answer(['error' => 'invalid_grant', 'error_description' => 'Invalid JWT Signature.'], 400);
    expect(fn () => $fcm->send(['live-phone'], 'T', 'B', []))->toThrow(RuntimeException::class);
    expect(ServiceHealth::last('fcm'))->toMatchArray(['problem' => 'key', 'code' => 'invalid_grant']);
});

it('notes a refused LiveKit key, which a lesson\'s join would otherwise only log', function () {
    $livekit = new LiveKitProvider(['url' => 'wss://lk.example', 'api_url' => 'https://lk.example', 'key' => 'APIkey', 'secret' => str_repeat('s', 32), 'max_participants' => 4]);
    $room = new Room(['name' => 'lesson-1']);

    answering('lk.example/*');
    answer(['code' => 'unauthenticated', 'msg' => 'invalid token'], 401);
    expect(fn () => $livekit->participants($room))->toThrow(RuntimeException::class);
    expect(ServiceHealth::last('livekit'))->toMatchArray(['problem' => 'key', 'code' => 'unauthenticated', 'scope' => 'rooms']);

    answer(['participants' => []]);
    expect($livekit->participants($room))->toBe([])->and(ServiceHealth::failing('livekit'))->toBeFalse();
});

it('notes Stripe\'s answers on the platform\'s key only', function () {
    config(['billing.payments.stripe.platform_secret' => 'sk_test_platform', 'billing.payments.stripe.connect_base' => 'https://connect.stripe.com', 'billing.payments.stripe.base' => 'https://api.stripe.com']);

    answering('*');
    answer(['error' => 'invalid_client', 'error_description' => 'No such application'], 401);
    expect(fn () => app(StripeConnect::class)->exchange('ac_code'))->toThrow(GatewayException::class);
    expect(ServiceHealth::last('stripe'))->toMatchArray(['problem' => 'key', 'code' => 'invalid_client']);
    ServiceHealth::forget('stripe');

    // An academy that disconnected its account from the platform: the academy's business.
    answer(['error' => ['type' => 'invalid_request_error', 'message' => 'The provided key does not have access to account']], 403);
    expect((new StripeGateway(['account_id' => 'acct_1']))->check())->not->toBeNull();
    expect(ServiceHealth::last('stripe'))->toBe([]);

    // An academy's own key: not the platform's either.
    answer(['error' => ['type' => 'invalid_request_error', 'code' => 'api_key_expired']], 401);
    expect((new StripeGateway(['secret_key' => 'sk_test_academy']))->check())->not->toBeNull();
    expect(ServiceHealth::last('stripe'))->toBe([]);

    answer(['available' => []]);
    expect((new StripeGateway(['account_id' => 'acct_1']))->check())->toBeNull();
    expect(ServiceHealth::last('stripe'))->toHaveKey('ok');
});
