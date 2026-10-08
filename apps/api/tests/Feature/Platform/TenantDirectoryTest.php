<?php

use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Enums\DomainKind;
use Modules\Platform\Models\Academy;

beforeEach(function () {
    $this->academy = Academy::factory()->create(['slug' => 'noor']);
    $this->directory = app(TenantDirectory::class);
});

it('finds an academy by its subdomain, ignoring case and port', function (string $host) {
    expect($this->directory->byHost($host)?->id)->toBe($this->academy->id);
})->with(['noor.lms.test', 'NOOR.lms.test', 'noor.lms.test:8443']);

it('ignores hosts that are not an academy', function (string $host) {
    expect($this->directory->byHost($host))->toBeNull();
})->with(['lms.test', 'other.lms.test', 'a.noor.lms.test', 'noor.lms.test.evil.com', '']);

it('finds an academy by a verified custom domain only', function () {
    $this->academy->domains()->create(['host' => 'learn.noor.sa', 'kind' => DomainKind::Custom]);
    expect($this->directory->byHost('learn.noor.sa'))->toBeNull();

    $this->academy->domains()->where('host', 'learn.noor.sa')->update(['verified_at' => now()]);
    expect($this->directory->byHost('learn.noor.sa')?->id)->toBe($this->academy->id);
});

it('links the home academy to the platform\'s own address and the others to their subdomains', function () {
    $other = Academy::factory()->create(['slug' => 'huda']);
    config(['app.url' => 'https://lms.test', 'platform.home_academy' => 'noor']);

    expect($this->directory->baseUrl($this->academy->toTenantRef()))->toBe('https://lms.test')
        ->and($this->directory->baseUrl($other->toTenantRef()))->toBe('https://huda.lms.test');
});

it('looks academies up by id without choking on bad input', function () {
    expect($this->directory->byId($this->academy->id)?->slug)->toBe('noor')
        ->and($this->directory->byId('not-a-uuid'))->toBeNull();
});

it('creates an academy with its owner from the command line', function () {
    $this->artisan('academy:create', [
        'slug' => 'huda',
        '--name-ar' => 'أكاديمية الهدى',
        '--name-en' => 'Huda Academy',
        '--currency' => 'kwd',
        '--timezone' => 'Asia/Kuwait',
        '--country' => 'kw',
        '--owner-email' => 'owner@huda.test',
        '--owner-password' => 'secret-pass',
    ])->assertSuccessful();

    $academy = Academy::query()->where('slug', 'huda')->sole();
    expect($academy->base_currency)->toBe('KWD')
        ->and($academy->domains()->sole()->host)->toBe('huda.lms.test');

    // The password is right; an owner sets up two-step sign-in in the back office before an app may sign in.
    $this->postJson('http://huda.lms.test/api/v1/auth/login', [
        'login' => 'owner@huda.test', 'password' => 'secret-pass', 'device_name' => 'cli-test',
    ])->assertForbidden()->assertJsonPath('type', 'two-factor-setup-required');
});

it('refuses reserved and duplicate slugs', function (string $slug) {
    $this->artisan('academy:create', [
        'slug' => $slug, '--owner-email' => 'x@example.test', '--owner-password' => 'secret-pass',
    ])->assertFailed();
})->with(['admin', 'noor', 'Bad_Slug']);
