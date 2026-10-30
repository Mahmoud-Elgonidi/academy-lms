<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Scheduling\Filament\Pages\DstChanges;
use Modules\Scheduling\Filament\Pages\PolicySettings;
use Modules\Scheduling\Filament\Pages\ShiftProposals;
use Modules\SharedKernel\Filament\ZoneChip;
use Modules\SharedKernel\Time\ZoneHint;

/*
 * The zone chip in the corner of every panel (SharedKernel ZoneChip,
 * shared::chrome.zone): the person's own zone and its time; opened, the
 * academy's and the device's when they keep another clock, and a way to
 * change the person's own zone, saved by the module that owns the account.
 */

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 12:05', 'UTC'));   // 14:05 in Cairo, 15:05 in Riyadh
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'default_timezone' => 'Asia/Riyadh']));
    $this->owner = User::factory()->withRole(Role::Owner)->create(['locale' => 'ar', 'timezone' => 'Africa/Cairo']);
    $this->teacher = User::factory()->withRole(Role::Teacher)->create(['locale' => 'ar', 'timezone' => 'Asia/Riyadh']);
    $this->guardian = User::factory()->withRole(Role::Guardian)->create(['locale' => 'ar', 'timezone' => 'Asia/Riyadh']);
    $this->clearTenant();
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('shows the chip with the city and the time on every academy panel', function (string $who, string $path, string $city, string $time) {
    $this->actingAs($this->{$who})->followingRedirects()->get("http://noor.lms.test/{$path}")
        ->assertOk()
        ->assertSee('class="lms-zone-pill"', false)
        ->assertSee($city)
        ->assertSee($time)
        ->assertSee('window.LmsZone', false)
        ->assertSee('data-hour-cycle="h12"', false)
        ->assertDontSee('onclick=', false);
})->with([
    'back office' => ['owner', 'admin', 'القاهرة', '2:05 م'],
    'teachers' => ['teacher', 'teach', 'الرياض', '3:05 م'],
    'family' => ['guardian', 'my', 'الرياض', '3:05 م'],
]);

it('stays off the sign-in page', function () {
    $this->get('http://noor.lms.test/admin/login')->assertOk()->assertDontSee('class="lms-zone-pill"', false);
});

it('shows the chip in the console on the admin\'s own zone, and saves it there', function () {
    $admin = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@platform.test', 'password' => 'a-long-password', 'timezone' => 'Europe/Istanbul']);
    $admin->saveAppAuthenticationSecret('LMSDEMOPLATFORMCONSOLE2FASECRET2');

    $this->actingAs($admin, 'platform')->get('/console/academies')
        ->assertOk()
        ->assertSee('class="lms-zone-pill"', false)
        ->assertSee('إسطنبول');

    Filament::setCurrentPanel('console');
    app()->setLocale('ar');
    Livewire::test(ZoneChip::class)
        ->assertDontSee(__('shared::chrome.zone.academy'))   // no academy in the console
        ->call('choose', 'Asia/Dubai')
        ->assertRedirect();

    expect($admin->fresh()->timezone)->toBe('Asia/Dubai');
});

it('changes the person\'s own zone from the chip, as My settings does', function () {
    $this->actAsTenant($this->academy);
    $this->actingAs($this->owner);
    Filament::setCurrentPanel('academy');
    app()->setLocale('ar');

    Livewire::test(ZoneChip::class)
        ->assertSee('القاهرة (UTC+2)')
        ->assertSee(__('shared::chrome.zone.academy'))          // Riyadh keeps another clock
        ->assertSee('الرياض (UTC+3)')
        ->set('changing', true)
        ->assertSee(__('shared::chrome.zone.search'))
        ->assertSee('data-search="دبي asia/dubai utc+4"', false)
        ->call('choose', 'Asia/Dubai')
        ->assertRedirect();

    expect($this->owner->fresh()->timezone)->toBe('Asia/Dubai');

    // Offsets are not zones: the person reads why (a refusal shows as a notice), and nothing changes.
    Livewire::test(ZoneChip::class)->call('choose', '+03:00')->assertNotified(__('shared::chrome.zone.invalid'));
    expect($this->owner->fresh()->timezone)->toBe('Asia/Dubai');
});

it('marks a device that keeps another clock, and offers to use it', function () {
    $this->actAsTenant($this->academy);
    $this->actingAs($this->teacher);
    Filament::setCurrentPanel('teach');
    app()->setLocale('ar');

    // The same clock (Kuwait keeps Riyadh's): nothing to say.
    Livewire::test(ZoneChip::class)
        ->call('noteDevice', 'Asia/Kuwait')
        ->assertDontSee('lms-zone-dot', false)
        ->assertDontSee(__('shared::chrome.zone.use_device'));

    $chip = Livewire::test(ZoneChip::class)
        ->call('noteDevice', 'Europe/London')
        ->assertSee('lms-zone-dot', false)
        ->assertSee('data-mismatch="true"', false)
        ->assertSee(__('shared::chrome.zone.device'))
        ->assertSee('لندن')
        ->assertSee(__('shared::chrome.zone.use_device'));
    expect(session(ZoneChip::DEVICE))->toBe('Europe/London');

    // The next page knows the device before its script runs.
    Livewire::test(ZoneChip::class)->assertSet('device', 'Europe/London')->assertSee('lms-zone-dot', false);

    // A device zone that is not one is ignored.
    Livewire::test(ZoneChip::class)->call('noteDevice', 'Mars/Olympus')->assertSet('device', 'Europe/London');

    $chip->call('useDevice')->assertRedirect();
    expect($this->teacher->fresh()->timezone)->toBe('Europe/London');
});

it('says under the title which zone a page\'s times are on', function () {
    $this->actAsTenant($this->academy);
    $this->actingAs($this->owner);
    Filament::setCurrentPanel('academy');
    app()->setLocale('ar');

    expect(ZoneHint::yours())->toBe('المواعيد هنا بتوقيتك: القاهرة (UTC+2).');
    Livewire::test(DstChanges::class)->assertSee(ZoneHint::yours());
    Livewire::test(PolicySettings::class)->assertSee(ZoneHint::yours());
    Livewire::test(ShiftProposals::class)->assertSee(ZoneHint::teachers())
        ->set('tab', 'teachers')->assertSee(__('scheduling::review.zone', ['zone' => 'الرياض']));   // each teacher's own clock

    $this->clearTenant();
    $admin = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@platform.test', 'password' => 'a-long-password', 'timezone' => 'Africa/Cairo']);
    $admin->saveAppAuthenticationSecret('LMSDEMOPLATFORMCONSOLE2FASECRET2');
    $this->actingAs($admin, 'platform');
    foreach (['/console/academies', '/console/academies/'.$this->academy->id, '/console/audit', '/console/health', '/console/signup-requests'] as $page) {
        $this->get($page)->assertOk()->assertSee('المواعيد هنا بتوقيتك: القاهرة (UTC+2).');
    }
});
