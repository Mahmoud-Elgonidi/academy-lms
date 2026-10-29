<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Identity\Actions\SetOwnPassword;
use Modules\Identity\Enums\Role;
use Modules\Identity\Filament\Pages\ChoosePassword;
use Modules\Identity\Filament\Pages\MySettings;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Audit\AuditActor;

/*
 * An owner who came in by a one-time code or a sign-in link has no password,
 * and two-step sign-in is set up against one: they choose it first
 * (ChoosePassword), then go on to the set-up, then to the back office.
 */

beforeEach(function () {
    $this->noor = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->owner = User::factory()->withRole(Role::Owner, Role::Admin)->withoutTwoFactor()->create(['email' => 'hala@noor.test']);
    $this->owner->forceFill(['password' => null])->saveQuietly();
});

afterEach(fn () => AuditActor::forget());

it('sends a passwordless owner to choose a password, then on to the set-up, and keeps it hashed', function () {
    $choose = 'http://noor.lms.test/admin/choose-password';
    $setUp = 'http://noor.lms.test/admin/multi-factor-authentication/set-up';
    $this->clearTenant();
    $this->actingAs($this->owner)->get('http://noor.lms.test/admin/invoices')->assertRedirect($choose);
    $this->actingAs($this->owner)->get($setUp)->assertRedirect($choose);
    $this->actingAs($this->owner)->get($choose)->assertOk()->assertSee(__('identity::security.password.subtitle'));

    $this->actAsTenant($this->noor);
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->owner);
    Livewire::test(ChoosePassword::class)
        ->fillForm(['password' => 'short', 'password_confirmation' => 'short'])->call('save')->assertHasFormErrors(['password']);
    Livewire::test(ChoosePassword::class)
        ->fillForm(['password' => 'a-long-password', 'password_confirmation' => 'another-password'])->call('save')->assertHasFormErrors(['password_confirmation']);
    Livewire::test(ChoosePassword::class)
        ->fillForm(['password' => 'a-long-password', 'password_confirmation' => 'a-long-password'])->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect($setUp);

    $saved = DB::table('users')->where('id', $this->owner->id)->value('password');
    expect($saved)->not->toBe('a-long-password')->and(Hash::check('a-long-password', $saved))->toBeTrue()
        ->and(DB::table('audit_logs')->where('subject_id', $this->owner->id)->pluck('action')->all())->toContain('identity.password_chosen');

    // Now the set-up asks for that password, and the page sends them on.
    $this->owner->refresh();
    $this->clearTenant();
    $this->actingAs($this->owner)->get('http://noor.lms.test/admin/invoices')->assertRedirect($setUp);
    $this->actingAs($this->owner)->get($setUp)->assertOk();
    $this->actingAs($this->owner)->get($choose)->assertRedirect();
});

it('chooses only a first password, and never while the platform team is signed in as them', function () {
    AuditActor::use(new AuditActor('platform', '0190f0a0-0000-7000-8000-0000000000aa', 'سلمى', $this->owner->id));
    expect(fn () => app(SetOwnPassword::class)->handle($this->owner, 'a-long-password'))->toThrow(ValidationException::class);
    AuditActor::forget();

    app(SetOwnPassword::class)->handle($this->owner, 'a-long-password');
    expect(fn () => app(SetOwnPassword::class)->handle($this->owner, 'another-long-one'))->toThrow(ValidationException::class)
        ->and(Hash::check('a-long-password', (string) $this->owner->refresh()->password))->toBeTrue();
});

it('sends someone with no password from My settings to choose one before two-step sign-in, and back', function () {
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->owner);
    $this->owner->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->saveQuietly();   // past the required set-up

    Livewire::test(MySettings::class)
        ->assertSee(__('identity::security.password.settings_hint'))
        ->assertDontSee(__('filament-panels::auth/multi-factor/app/actions/disable.label'));

    Livewire::withQueryParams(['back' => 'settings'])->test(ChoosePassword::class)
        ->fillForm(['password' => 'a-long-password', 'password_confirmation' => 'a-long-password'])->call('save')
        ->assertRedirect(MySettings::getUrl());
    Livewire::test(MySettings::class)->assertDontSee(__('identity::security.password.settings_hint'));
});
