<?php

use Illuminate\Support\Facades\Log;
use Modules\SharedKernel\Http\SecurityHeaders;
use Modules\SharedKernel\Logging\RedactPersonalData;

/*
 * HTTP hardening (docs/architecture/03, section 11): the headers on every
 * answer, HSTS over https, the API's ceiling, and log lines without people.
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
