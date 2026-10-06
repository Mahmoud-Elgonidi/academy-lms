<?php

use Carbon\CarbonImmutable;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

/*
 * The panels' frame (SharedKernel chrome/*): the same look and page changes in
 * every panel, and the side menu's greeting by the time of day where the
 * viewer is.
 */

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'default_timezone' => 'Africa/Cairo']));
    $this->owner = User::factory()->withRole(Role::Owner)->create(['name' => 'أ. سارة عبد الرحمن', 'locale' => 'ar']);
    $this->clearTenant();
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('gives every panel the frame and its page changes, from the sign-in page on', function (string $panel) {
    $this->get("http://noor.lms.test/{$panel}/login")
        ->assertOk()
        ->assertSee('--lms-pill', false)                              // chrome/styles
        ->assertSee('wire:transition.navigate="lms-shell"', false)    // lets Livewire run a view transition on each page change
        ->assertSee('window.LmsChrome', false);
})->with(['admin', 'teach', 'my']);

it('greets by the time of day on the viewer\'s own clock, by first name past a title', function (string $timezone, string $sky, string $hello) {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 05:30', 'UTC'));

    $this->actAsTenant($this->academy);
    $this->owner->forceFill(['timezone' => $timezone])->save();
    $this->clearTenant();

    $this->actingAs($this->owner)->get('http://noor.lms.test/admin')
        ->assertOk()
        ->assertSee('data-sky="'.$sky.'"', false)
        ->assertSee($hello.'، سارة')
        ->assertSee('الثلاثاء 6 أكتوبر');
})->with([
    'morning in Riyadh (08:30)' => ['Asia/Riyadh', 'morning', 'صباح الخير'],
    'night in New York (01:30)' => ['America/New_York', 'night', 'مساء الخير'],
    'afternoon in Tokyo (14:30)' => ['Asia/Tokyo', 'day', 'نهارك سعيد'],
]);
