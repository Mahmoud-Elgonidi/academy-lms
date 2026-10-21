<?php

use Carbon\CarbonImmutable;
use Modules\Platform\Health\OutsideServices;
use Modules\Platform\Health\SystemHealth;
use Modules\SharedKernel\Health\ServiceHealth;
use Modules\SharedKernel\Health\ServiceProblem;

/*
 * How each outside service answers (docs/architecture/03, section 12): the
 * gateways, "Try it" and the daily check write a service's last word and why
 * it failed, in our words with the provider's code only; the health page has
 * one check per service set up here, which fails at once when a person must
 * act (a key, the credit) and only when it lasts for a busy moment; GET
 * /health's 503 stays about the stack.
 */

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-20 10:00:00');
    foreach (array_keys(OutsideServices::GROUPS) as $service) {
        ServiceHealth::forget($service);
    }
    app()->setLocale('en');
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('keeps a service\'s last word: a failure, how many in a row since when, and what clears it', function () {
    ServiceHealth::failed('openai', ServiceProblem::Quota, 'insufficient_quota');
    CarbonImmutable::setTestNow('2026-10-20 10:05:00');
    ServiceHealth::failed('openai', ServiceProblem::Quota, 'insufficient_quota');

    expect(ServiceHealth::failing('openai'))->toBeTrue()
        ->and(ServiceHealth::problem('openai'))->toBe(ServiceProblem::Quota)
        ->and(ServiceHealth::last('openai'))->toMatchArray(['in_a_row' => 2, 'code' => 'insufficient_quota', 'since' => CarbonImmutable::parse('2026-10-20 10:00:00')->getTimestamp()]);

    ServiceHealth::ok('openai');
    expect(ServiceHealth::failing('openai'))->toBeFalse()->and(ServiceHealth::last('openai'))->not->toHaveKey('problem');
});

it('lets a model\'s failure stand until that model, or a check, answers', function () {
    ServiceHealth::failed('anthropic', ServiceProblem::Model, 'not_found_error', scope: 'claude-retired');
    ServiceHealth::ok('anthropic', 'claude-haiku-5-5');
    expect(ServiceHealth::problem('anthropic'))->toBe(ServiceProblem::Model)->and(ServiceHealth::last('anthropic'))->toHaveKey('seen');

    ServiceHealth::ok('anthropic', 'claude-retired');
    expect(ServiceHealth::failing('anthropic'))->toBeFalse();

    ServiceHealth::failed('anthropic', ServiceProblem::Model, 'not_found_error', scope: 'claude-retired');
    ServiceHealth::ok('anthropic', 'claude-haiku-5-5', via: 'check');
    expect(ServiceHealth::failing('anthropic'))->toBeFalse()->and(ServiceHealth::last('anthropic'))->toHaveKey('checked');
});

it('keeps a provider\'s code, never its words', function () {
    ServiceHealth::failed('whatsapp', ServiceProblem::Key, 'Invalid OAuth access token - Cannot parse access token "EAAG…" for hala@noor.test');

    expect(ServiceHealth::last('whatsapp')['code'])->not->toContain('@')->not->toContain('"')->not->toContain('…')
        ->and(mb_strlen(ServiceHealth::last('whatsapp')['code']))->toBeLessThanOrEqual(60);
});

it('writes a success at most once a minute while all is well', function () {
    ServiceHealth::ok('whatsapp');
    $first = ServiceHealth::last('whatsapp')['ok'];
    CarbonImmutable::setTestNow('2026-10-20 10:00:30');
    ServiceHealth::ok('whatsapp');
    expect(ServiceHealth::last('whatsapp')['ok'])->toBe($first);
    CarbonImmutable::setTestNow('2026-10-20 10:01:30');
    ServiceHealth::ok('whatsapp');
    expect(ServiceHealth::last('whatsapp')['ok'])->toBeGreaterThan($first);
});

it('gives the health page one check per outside service set up here', function () {
    config(['platform.ai.driver' => 'claude', 'platform.ai.key' => 'k', 'platform.ai.speech.key' => '', 'platform.games.designer.key' => '',
        'communication.whatsapp.driver' => 'meta', 'communication.whatsapp.meta.token' => 't', 'communication.whatsapp.meta.phone_number_id' => '1234567',
        'communication.sms.driver' => 'log', 'communication.push.driver' => 'log', 'classroom.livekit.key' => '', 'billing.payments.stripe.platform_secret' => '']);

    expect(OutsideServices::configured())->toBe(['anthropic', 'whatsapp'])
        ->and(array_keys(app(SystemHealth::class)->serviceChecks()))->toBe(['service_anthropic', 'service_whatsapp']);

    config(['platform.ai.driver' => 'off']);
    expect(OutsideServices::configured())->toBe(['whatsapp']);
});

it('fails a service at once when a person must act, and a busy one only when it lasts', function () {
    $health = app(SystemHealth::class);

    expect($health->service('openai'))->toMatchArray(['status' => 'ok', 'value' => __('platform::services.used.never')]);

    ServiceHealth::failed('openai', ServiceProblem::Quota, 'insufficient_quota');
    expect($health->service('openai'))->toMatchArray(['status' => 'fail'])
        ->and($health->service('openai')['value'])->toContain(__('platform::services.short.quota'))->toContain('insufficient_quota')
        ->and($health->service('openai')['hint'])->toContain(__('platform::services.todo.quota', ['service' => 'GPT (OpenAI)']));

    // Down: nothing after one, a word after three, down when it lasts a quarter of an hour.
    ServiceHealth::failed('gemini', ServiceProblem::Down, 'HTTP 503');
    expect($health->service('gemini')['status'])->toBe('ok');
    ServiceHealth::failed('gemini', ServiceProblem::Down, 'HTTP 503');
    ServiceHealth::failed('gemini', ServiceProblem::Down, 'HTTP 503');
    expect($health->service('gemini')['status'])->toBe('warn');
    CarbonImmutable::setTestNow('2026-10-20 10:16:00');
    expect($health->service('gemini')['status'])->toBe('fail');

    // Too many requests: down only after an hour of it.
    foreach (range(1, 3) as $i) {
        ServiceHealth::failed('twilio', ServiceProblem::Rate, '20429');
    }
    expect($health->service('twilio')['status'])->toBe('warn');
    CarbonImmutable::setTestNow('2026-10-20 11:20:00');
    expect($health->service('twilio')['status'])->toBe('fail');
});

it('warns when a plan is nearly used up, or the daily check has not run for days', function () {
    $health = app(SystemHealth::class);
    ServiceHealth::near('elevenlabs', 93);
    expect($health->service('elevenlabs'))->toMatchArray(['status' => 'warn', 'value' => __('platform::services.used.near', ['pct' => 93])]);
    ServiceHealth::near('elevenlabs', null);
    expect($health->service('elevenlabs')['status'])->toBe('ok');

    ServiceHealth::ok('stripe', via: 'check');
    expect($health->service('stripe')['status'])->toBe('ok')->and($health->service('stripe')['value'])->toContain('ago');
    CarbonImmutable::setTestNow('2026-10-24 10:00:00');
    expect($health->service('stripe')['status'])->toBe('warn');
});

it('keeps GET /health\'s 503 about the stack, an outside service\'s trouble only listed', function () {
    config(['platform.health.token' => 'monitor-token']);
    $this->mock(SystemHealth::class, fn ($m) => $m->shouldReceive('checks')->andReturn([
        'database' => ['status' => 'ok', 'value' => 'answers', 'hint' => null],
        'service_openai' => ['status' => 'fail', 'value' => 'credit ran out', 'hint' => 'top up'],
    ]));

    $this->withToken('monitor-token')->getJson('/health')->assertOk()
        ->assertJson(['status' => 'ok', 'checks' => ['database' => 'ok', 'service_openai' => 'fail']]);
});
