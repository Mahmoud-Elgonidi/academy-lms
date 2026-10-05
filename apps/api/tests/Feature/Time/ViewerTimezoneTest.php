<?php

use Carbon\CarbonImmutable;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\SharedKernel\Time\ViewerTimezone;

beforeEach(function () {
    // Academy default: Riyadh. Khaled's profile: London.
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'default_timezone' => 'Asia/Riyadh']));
    $this->user = User::factory()->withRole(Role::Guardian)->create(['email' => 'khaled@example.test', 'timezone' => 'Europe/London']);
    $this->clearTenant();
    $this->token = $this->withHeader('X-Academy', 'noor')->postJson('/api/v1/auth/login', [
        'login' => 'khaled@example.test', 'password' => 'password', 'device_name' => 'phone',
    ])->json('token');
});

it('shows times in the profile timezone when the device sends none', function () {
    $this->withToken($this->token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertHeader('X-Timezone', 'Europe/London')
        ->assertJsonPath('data.timezone', 'Europe/London')
        ->assertJsonPath('data.display_timezone', 'Europe/London')
        ->assertJsonPath('data.timezone_mismatch', false);
});

it('follows the device timezone and flags a traveller', function () {
    $this->withToken($this->token)->withHeader('X-Timezone', 'Asia/Dubai')->getJson('/api/v1/me')
        ->assertOk()
        ->assertHeader('X-Timezone', 'Asia/Dubai')
        ->assertJsonPath('data.display_timezone', 'Asia/Dubai')
        ->assertJsonPath('data.timezone_mismatch', true);
});

it('does not flag a device zone that keeps the same clock as the profile', function () {
    $this->actAsTenant($this->academy);
    $this->user->update(['timezone' => 'Asia/Riyadh']);
    $this->clearTenant();

    $this->withToken($this->token)->withHeader('X-Timezone', 'Asia/Kuwait')->getJson('/api/v1/me')
        ->assertJsonPath('data.display_timezone', 'Asia/Kuwait')
        ->assertJsonPath('data.timezone_mismatch', false);
});

it('ignores a device timezone that is not an IANA name', function () {
    $this->withToken($this->token)->withHeader('X-Timezone', 'GMT+4')->getJson('/api/v1/me')
        ->assertHeader('X-Timezone', 'Europe/London')
        ->assertJsonPath('data.display_timezone', 'Europe/London');
});

it('falls back to the academy timezone for a user without one', function () {
    $this->actAsTenant($this->academy);
    $this->user->update(['timezone' => null]);
    $this->clearTenant();

    $this->withToken($this->token)->getJson('/api/v1/me')
        ->assertJsonPath('data.display_timezone', 'Asia/Riyadh');
});

it('lets the traveller move their profile to the device timezone', function () {
    $this->withToken($this->token)->withHeader('X-Timezone', 'Asia/Dubai')
        ->patchJson('/api/v1/me', ['timezone' => 'Asia/Dubai'])
        ->assertOk()
        ->assertJsonPath('data.timezone', 'Asia/Dubai')
        ->assertJsonPath('data.timezone_mismatch', false);
});

it('refuses an offset as a timezone', function () {
    $this->withToken($this->token)->patchJson('/api/v1/me', ['timezone' => '+04:00'])
        ->assertUnprocessable()
        ->assertJsonPath('type', 'validation')
        ->assertJsonStructure(['errors' => ['timezone']]);
});

it('writes the login expiry in the new user\'s timezone', function () {
    $response = $this->withHeader('X-Academy', 'noor')->withHeader('Accept-Language', 'ar')->postJson('/api/v1/auth/login', [
        'login' => 'khaled@example.test', 'password' => 'password', 'device_name' => 'tablet',
    ])->assertOk();

    expect($response->json('expires_at.timezone'))->toBe('Europe/London')
        ->and($response->json('expires_at.utc'))->toEndWith('Z')
        ->and($response->json('user.display_timezone'))->toBe('Europe/London');
});

it('presents one instant as UTC, local time and a translated label', function () {
    $instant = CarbonImmutable::parse('2026-10-05 19:00:00', 'UTC');
    app()->setLocale('ar');
    app(ViewerTimezone::class)->setDevice('Asia/Riyadh');

    expect(app(LocalTimePresenter::class)->present($instant))->toBe([
        'utc' => '2026-10-05T19:00:00Z',
        'local' => '2026-10-05T22:00:00+03:00',
        'timezone' => 'Asia/Riyadh',
        'label' => 'الاثنين 5 أكتوبر · 22:00',
    ]);

    app()->setLocale('en');
    expect(app(LocalTimePresenter::class)->present($instant, 'America/New_York')['label'])->toBe('Mon 5 Oct · 15:00')
        ->and(app(LocalTimePresenter::class)->present(null))->toBeNull();
});
