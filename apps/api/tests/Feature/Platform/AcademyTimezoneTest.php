<?php

use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\SaveAcademyTimezone;
use Modules\Platform\Filament\Pages\BrandingSettings;
use Modules\Platform\Models\Academy;
use Modules\SharedKernel\Tenancy\TenantContext;

/*
 * The academy's own zone (Settings › Branding): times for accounts with no
 * zone of their own and the academy's holidays and days. The owner alone
 * changes it (SaveAcademyTimezone), audited; the admins read it.
 */

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'default_timezone' => 'Asia/Riyadh']));
    $this->owner = User::factory()->withRole(Role::Owner)->create(['locale' => 'ar']);
    $this->admin = User::factory()->withRole(Role::Admin)->create(['locale' => 'ar']);
    Filament::setCurrentPanel('academy');
});

it('lets the owner change the academy\'s zone from Branding, audited', function () {
    $this->actingAs($this->owner);

    Livewire::test(BrandingSettings::class)
        ->assertSet('data.default_timezone', 'Asia/Riyadh')
        ->assertSee(__('platform::branding.timezone.hint'))
        ->set('data.default_timezone', 'Africa/Cairo')
        ->call('save')
        ->assertHasNoErrors();

    expect(Academy::query()->find($this->academy->id)->default_timezone)->toBe('Africa/Cairo')
        ->and(app(TenantContext::class)->require()->defaultTimezone)->toBe('Africa/Cairo');
    $audit = DB::table('audit_logs')->where('action', 'academy.timezone_saved')->sole();
    expect(json_decode($audit->data, true))->toEqual(['from' => 'Asia/Riyadh', 'to' => 'Africa/Cairo']);

    // The same zone again is no change, and no line.
    app(SaveAcademyTimezone::class)->handle('Africa/Cairo');
    expect(DB::table('audit_logs')->where('action', 'academy.timezone_saved')->count())->toBe(1);

    expect(fn () => app(SaveAcademyTimezone::class)->handle('+02:00'))->toThrow(ValidationException::class);
});

it('shows the zone to admins without letting them change it', function () {
    $this->actingAs($this->admin);

    Livewire::test(BrandingSettings::class)
        ->assertSee(__('platform::branding.timezone.owner_only'))
        ->assertFormFieldIsDisabled('default_timezone')
        ->set('data.default_timezone', 'Africa/Cairo')
        ->call('save');

    expect(Academy::query()->find($this->academy->id)->default_timezone)->toBe('Asia/Riyadh')
        ->and(fn () => app(SaveAcademyTimezone::class)->handle('Africa/Cairo'))->toThrow(AuthorizationException::class);
});
