<?php

use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Identity\Contracts\Preferences\UserPreferences;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Filament\Pages\LiveBoard;

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->owner = User::factory()->withRole(Role::Owner)->create(['locale' => 'ar']);
    $this->teacher = User::factory()->withRole(Role::Teacher)->create();
    $this->accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->clearTenant();
});

it('serves the sign-in page on the academy\'s own host, in Arabic and right to left', function () {
    $this->get('http://noor.lms.test/admin/login')
        ->assertOk()
        ->assertSee('dir="rtl"', false);
});

it('opens the live board for staff', function () {
    $this->actingAs($this->owner)->get('http://noor.lms.test/admin')
        ->assertOk()
        ->assertSee(__('scheduling::board.title', [], 'ar'));
});

it('keeps teachers out of the back office', function () {
    $this->actingAs($this->teacher)->get('http://noor.lms.test/admin')->assertForbidden();
});

it('shows identity and colours to owners and admins', function () {
    $this->actingAs($this->owner)->get('http://noor.lms.test/admin/settings/branding')->assertOk();
});

it('hides identity and colours from other staff', function () {
    $this->actingAs($this->accountant)->get('http://noor.lms.test/admin/settings/branding')->assertForbidden();
});

it('remembers the board layout per user, from the screen or from settings', function () {
    $this->actAsTenant($this->academy);
    $preferences = app(UserPreferences::class);

    expect($preferences->get($this->owner->id, LiveBoard::LAYOUT_PREFERENCE))->toBe('table');

    $preferences->set($this->owner->id, LiveBoard::LAYOUT_PREFERENCE, 'focus');
    expect($preferences->get($this->owner->id, LiveBoard::LAYOUT_PREFERENCE))->toBe('focus');

    expect(fn () => $preferences->set($this->owner->id, LiveBoard::LAYOUT_PREFERENCE, 'kanban'))
        ->toThrow(ValidationException::class);
});

it('saves the layout switch on the board for the signed-in user', function () {
    $this->actAsTenant($this->academy);
    $this->actingAs($this->owner);
    Filament\Facades\Filament::setCurrentPanel('academy');

    Livewire::test(LiveBoard::class)
        ->assertSet('boardLayout', 'table')
        ->call('setLayout', 'focus')
        ->assertSet('boardLayout', 'focus');

    expect($this->owner->refresh()->preferences)->toBe(['live_board.layout' => 'focus']);
});

it('answers 404, not a server error, for the back office on a host that is no academy', function () {
    $this->get('http://nowhere.lms.test/admin/login')->assertNotFound();
});
