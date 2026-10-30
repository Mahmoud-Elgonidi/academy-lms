<?php

use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Time\Timezones;

/*
 * Zones for the apps and the portal: GET me says the zone it writes in and
 * the academy's as people read them (timezone_label), and GET timezones
 * lists every zone to choose from, cities in the reader's language.
 */

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'default_timezone' => 'Asia/Riyadh']));
    $this->guardian = User::factory()->withRole(Role::Guardian)->create(['email' => 'khaled@example.test', 'timezone' => 'Africa/Cairo']);
    $this->clearTenant();
    $this->token = $this->withHeader('X-Academy', 'noor')->postJson('/api/v1/auth/login', [
        'login' => 'khaled@example.test', 'password' => 'password', 'device_name' => 'phone',
    ])->json('token');
});

it('labels the zones in GET me in the reader\'s language', function () {
    $offset = fn (string $tz) => Timezones::offset($tz);

    $this->withToken($this->token)->withHeaders(['X-Academy' => 'noor', 'Accept-Language' => 'ar'])->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.display_timezone', 'Africa/Cairo')
        ->assertJsonPath('data.timezone_label', 'القاهرة ('.$offset('Africa/Cairo').')')
        ->assertJsonPath('data.academy.timezone_label', 'الرياض (UTC+3)');

    // On the device's zone when it sends one.
    $this->withToken($this->token)->withHeaders(['X-Academy' => 'noor', 'Accept-Language' => 'en', 'X-Timezone' => 'Europe/Istanbul'])->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.display_timezone', 'Europe/Istanbul')
        ->assertJsonPath('data.timezone_label', 'Istanbul (UTC+3)')
        ->assertJsonPath('data.academy.timezone_label', 'Riyadh (UTC+3)');
});

it('lists every zone to choose from, by region then offset', function () {
    $this->withHeaders(['X-Academy' => 'noor'])->getJson('/api/v1/timezones')->assertUnauthorized();
    $rows = $this->withToken($this->token)->withHeaders(['X-Academy' => 'noor', 'Accept-Language' => 'ar'])->getJson('/api/v1/timezones')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'city', 'offset', 'label', 'region']]])
        ->json('data');

    expect(count($rows))->toBe(count(Timezones::all()));
    $cairo = collect($rows)->firstWhere('id', 'Africa/Cairo');
    expect($cairo['city'])->toBe('القاهرة')
        ->and($cairo['region'])->toBe('أفريقيا')
        ->and($cairo['label'])->toBe('القاهرة ('.$cairo['offset'].')')
        ->and(collect($rows)->firstWhere('id', 'Asia/Kolkata')['offset'])->toBe('UTC+5:30');

    // Asia first, then Africa; within a region, west to east.
    $regions = collect($rows)->pluck('region')->unique()->values()->all();
    expect(array_slice($regions, 0, 2))->toBe(['آسيا', 'أفريقيا']);
    $asia = collect($rows)->where('region', 'آسيا')->map(fn ($r) => (new DateTimeZone($r['id']))->getOffset(new DateTimeImmutable))->values()->all();
    $sorted = $asia;
    sort($sorted);
    expect($asia)->toBe($sorted);
});

it('says whether the zone chip shows, and keeps the choice from PATCH me', function () {
    $headers = ['X-Academy' => 'noor', 'Accept-Language' => 'ar'];
    $this->withToken($this->token)->withHeaders($headers)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.zone_chip', true);

    $this->withToken($this->token)->withHeaders($headers)->patchJson('/api/v1/me', ['zone_chip' => false])->assertOk()
        ->assertJsonPath('data.zone_chip', false)
        ->assertJsonPath('data.timezone', 'Africa/Cairo');   // nothing else changes
    $this->withToken($this->token)->withHeaders($headers)->getJson('/api/v1/me')->assertJsonPath('data.zone_chip', false);

    // Another change leaves it as it is; true brings it back; anything else is refused.
    $this->withToken($this->token)->withHeaders($headers)->patchJson('/api/v1/me', ['locale' => 'en'])->assertOk()->assertJsonPath('data.zone_chip', false);
    $this->withToken($this->token)->withHeaders($headers)->patchJson('/api/v1/me', ['zone_chip' => 'maybe'])->assertUnprocessable();
    $this->withToken($this->token)->withHeaders($headers)->patchJson('/api/v1/me', ['zone_chip' => true])->assertOk()->assertJsonPath('data.zone_chip', true);
});
