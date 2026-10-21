<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Modules\Platform\Health\OutsideServices;
use Modules\Platform\Health\SystemHealth;
use Modules\Platform\Settings\LastChecks;
use Modules\SharedKernel\Health\ServiceHealth;
use Modules\SharedKernel\Health\ServiceProblem;

/*
 * The daily check (docs/architecture/03, section 12): each morning the
 * outside services set up here are tried as "Try it" tries them, what they
 * answered is written down (so a credit run out shows on the health page and
 * reaches the platform team's alert on a quiet day), and the settings page
 * shows it as each section's last try. Email is never tried: it would send.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Mail::fake();
    app()->setLocale('en');
    foreach (array_keys(OutsideServices::GROUPS) as $service) {
        ServiceHealth::forget($service);
    }
    config([
        'platform.ai.driver' => 'off', 'platform.ai.key' => null, 'platform.ai.speech.key' => null,
        'platform.games.designer.key' => null, 'platform.games.designer.model' => null,
        'communication.whatsapp.driver' => 'log', 'communication.sms.driver' => 'log', 'communication.push.driver' => 'log',
        'classroom.livekit.key' => null, 'billing.payments.stripe.platform_secret' => null,
        'mail.default' => 'smtp',
    ]);
});

it('tries each service set up here, writes down how it answered, and fails when one needs someone', function () {
    config(['billing.payments.stripe.platform_secret' => 'sk_test_platform', 'billing.payments.stripe.connect_client_id' => 'ca_1',
        'communication.sms.driver' => 'twilio', 'communication.sms.twilio.sid' => 'AC'.str_repeat('d', 32), 'communication.sms.twilio.token' => 'twilio-1',
        // Keys for a WhatsApp not chosen as the sender: not set up here, so not tried.
        'communication.whatsapp.meta.token' => 'meta-1', 'communication.whatsapp.meta.phone_number_id' => '1']);
    $stripe = Http::response(['error' => ['code' => 'api_key_expired', 'type' => 'invalid_request_error']], 401);
    Http::fake([
        'api.stripe.com/v1/account' => function () use (&$stripe) {
            return $stripe;
        },
        'api.twilio.com/*/Balance.json' => Http::response(['balance' => '80', 'currency' => 'USD']),
        'api.twilio.com/*' => Http::response(['status' => 'active', 'type' => 'Full']),
    ]);

    $this->artisan('platform:services')
        ->expectsOutputToContain('Stripe refused the key: it is wrong, revoked or expired. (api_key_expired)')
        ->expectsOutputToContain('Twilio took the account; 80.00 USD left.')
        ->expectsOutputToContain('overall: fail')
        ->assertExitCode(1);

    expect(ServiceHealth::problem('stripe'))->toBe(ServiceProblem::Key)
        ->and(ServiceHealth::last('stripe')['via'])->toBe('check')
        ->and(ServiceHealth::last('twilio')['checked'])->toBeInt()
        ->and(ServiceHealth::last('whatsapp'))->toBe([])
        ->and(LastChecks::get('stripe')['by'])->toBeNull()
        ->and(LastChecks::get('sms')['result']->level())->toBe('ok')
        ->and(LastChecks::get('mail'))->toBeNull()
        ->and(app(SystemHealth::class)->serviceChecks()['service_stripe']['status'])->toBe('fail');
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'graph.facebook.com'));
    Mail::assertNothingSent();

    // Only some sections, and all well again.
    $stripe = Http::response(['charges_enabled' => true, 'requirements' => ['currently_due' => []]]);
    $this->artisan('platform:services --group=stripe')->expectsOutputToContain('overall: ok')->assertExitCode(0);
    expect(ServiceHealth::failing('stripe'))->toBeFalse();
});

it('does nothing where no outside service is set up', function () {
    Http::fake();

    $this->artisan('platform:services')->expectsOutput('no outside services are set up here')->assertExitCode(0);
    Http::assertNothingSent();
});

it('runs each morning, once, on one server', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'platform:services'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('40 6 * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});
