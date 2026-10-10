<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Auth\DummyPassword;
use Modules\SharedKernel\Http\SecurityHeaders;
use Modules\SharedKernel\Logging\RedactPersonalData;

/*
 * HTTP hardening (docs/architecture/03, section 11): the headers on every
 * answer, HSTS over https, the API's ceiling, no other site's pages calling
 * the API, sign-ins that take as long with or without an account, and log
 * lines without people.
 */

beforeEach(function () {
    $this->academy = $this->createAcademy(['slug' => 'huda']);
});

it('sends the security headers on pages, the API and errors, and HSTS only over https', function () {
    foreach (['http://huda.lms.test/up', 'http://huda.lms.test/api/v1/branding', 'http://huda.lms.test/admin/login', 'http://huda.lms.test/api/v1/nothing-here'] as $url) {
        $response = $this->get($url);
        foreach (SecurityHeaders::HEADERS as $name => $value) {
            expect($response->headers->get($name))->toBe($value, "{$name} on {$url}");
        }
        expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
    }

    expect($this->get('https://huda.lms.test/api/v1/branding')->headers->get('Strict-Transport-Security'))->toBe(SecurityHeaders::HSTS);
});

it('caps the apps\' API per address before signing in, each address on its own', function () {
    $statuses = collect(range(1, 241))->map(fn () => $this->getJson('http://huda.lms.test/api/v1/branding')->getStatusCode());

    expect($statuses->take(240)->unique()->all())->toBe([200])
        ->and($statuses->last())->toBe(429);
    $this->getJson('http://huda.lms.test/api/v1/branding')->assertStatus(429)->assertHeader('Retry-After');
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->getJson('http://huda.lms.test/api/v1/branding')->assertOk();
});

it('counts each tight door on its own, so using one never shuts another', function () {
    $this->actAsTenant($this->academy);
    $user = User::factory()->create();
    $api = fn () => $this->actingAs($user, 'sanctum')->withHeaders(['X-Academy' => 'huda']);

    // Twenty tries at taking back a request (throttle:20,1) ...
    $statuses = collect(range(1, 21))->map(fn () => $api()->postJson('/api/v1/me/data-requests/01a00000-0000-7000-8000-000000000000/cancel')->getStatusCode());
    expect($statuses->take(20)->unique()->all())->toBe([404])->and($statuses->last())->toBe(429);
    // ... leave the door for a new email (throttle:10,1) open.
    $api()->postJson('/api/v1/me/contact', ['kind' => 'email', 'value' => 'not an email'])->assertUnprocessable();
});

it('writes log lines without emails, phone numbers or secrets', function () {
    $path = storage_path('framework/testing/redact-'.uniqid().'.log');
    config(['logging.channels.redact_check' => ['driver' => 'single', 'path' => $path, 'tap' => [RedactPersonalData::class]]]);

    Log::channel('redact_check')->warning('Code sent to salma@demo.test on +966501234567', ['password' => 'hunter22', 'url' => 'https://huda.lms.test/rate/1?u=2&signature=abc']);
    $line = (string) file_get_contents($path);
    @unlink($path);

    expect($line)->toContain('***@demo.test')->toContain('***67')->toContain('"password":"[redacted]"')->toContain('signature=[redacted]')
        ->not->toContain('salma@')->not->toContain('501234567')->not->toContain('hunter22')->not->toContain('signature=abc');
});

it('taps every channel that writes lines', function () {
    foreach (['single', 'daily', 'stderr', 'syslog', 'errorlog'] as $channel) {
        expect(config("logging.channels.{$channel}.tap"))->toContain(RedactPersonalData::class);
    }
});

it('lets no other site\'s page call the API unless the server names it', function () {
    $preflight = fn (string $origin) => $this->call('OPTIONS', 'http://huda.lms.test/api/v1/auth/login', [], [], [], [
        'HTTP_ORIGIN' => $origin, 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST', 'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,x-academy',
    ]);

    expect(config('cors.allowed_origins'))->toBe([])
        ->and($preflight('https://evil.test')->headers->has('Access-Control-Allow-Origin'))->toBeFalse()
        ->and($this->withHeader('Origin', 'https://evil.test')->getJson('http://huda.lms.test/api/v1/branding')->headers->has('Access-Control-Allow-Origin'))->toBeFalse();

    config(['cors.allowed_origins' => ['https://app.huda.test']]);
    expect($preflight('https://app.huda.test')->headers->get('Access-Control-Allow-Origin'))->toBe('https://app.huda.test')
        // One named origin is always the answer, which a browser on any other page refuses.
        ->and($preflight('https://evil.test')->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.test')
        ->and($preflight('https://app.huda.test')->headers->has('Access-Control-Allow-Credentials'))->toBeFalse();
});

it('checks a password even when no account has that address, so the time says nothing', function () {
    $this->actAsTenant($this->academy);
    User::factory()->create(['email' => 'known@huda.test', 'password' => 'right-password']);
    DummyPassword::check('warm up');
    $checks = 0;
    Hash::partialMock()->shouldReceive('check')->andReturnUsing(function () use (&$checks) {
        $checks++;

        return false;
    });
    $attempts = function (callable $try) use (&$checks): int {
        $checks = 0;
        $try();

        return $checks;
    };

    // The apps' sign-in, and each panel's guard (staff, families, the platform team).
    $api = fn (string $login) => $this->postJson('http://huda.lms.test/api/v1/auth/login', ['login' => $login, 'password' => 'guess', 'device_name' => 'phone'])->assertStatus(422);
    expect($attempts(fn () => $api('nobody@huda.test')))->toBe(1)
        ->and($attempts(fn () => $api('known@huda.test')))->toBe(1)
        ->and($attempts(fn () => Auth::guard('web')->attempt(['email' => 'nobody@huda.test', 'password' => 'guess'])))->toBe(1)
        ->and($attempts(fn () => Auth::guard('web')->attempt(['email' => 'known@huda.test', 'password' => 'guess'])))->toBe(1)
        ->and($attempts(fn () => Auth::guard('platform')->attempt(['email' => 'nobody@platform.test', 'password' => 'guess'])))->toBe(1);
});
