<?php

use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Identity\Actions\SaveSecuritySettings;
use Modules\Identity\Enums\Role;
use Modules\Identity\Filament\Pages\SecuritySettings;
use Modules\Identity\Models\IdentitySettings;
use Modules\Identity\Models\User;
use Modules\Identity\TwoFactor\TwoFactor;
use Modules\SharedKernel\Audit\AuditActor;

/*
 * Whether two-step sign-in is required of the back office is the academy's
 * choice (Settings › Security, the owner alone; docs/architecture/02,
 * section 19): required at first; off, the owner, admins and accountants go
 * in without setting it up, on the panels and in the apps, and whoever has
 * it on is still asked for it.
 */

beforeEach(function () {
    $this->noor = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->owner = User::factory()->withRole(Role::Owner, Role::Admin)->withoutTwoFactor()->create(['email' => 'hala@noor.test', 'password' => 'a-long-password']);
    $this->admin = User::factory()->withRole(Role::Admin)->withoutTwoFactor()->create(['email' => 'amr@noor.test', 'password' => 'a-long-password']);
    $this->supervisor = User::factory()->withRole(Role::Supervisor)->create();
});

afterEach(fn () => AuditActor::forget());

function turnTwoFactorRule(User $owner, bool $required): void
{
    test()->actingAs($owner);
    app(SaveSecuritySettings::class)->handle($required);
}

it('is required of the owner, admins and accountants until the owner says otherwise, and kept in the log', function () {
    expect(TwoFactor::requiredFor($this->owner))->toBeTrue()
        ->and(TwoFactor::requiredFor($this->admin))->toBeTrue()
        ->and(TwoFactor::requiredFor($this->supervisor))->toBeFalse()
        ->and(IdentitySettings::query()->exists())->toBeFalse();

    turnTwoFactorRule($this->owner, false);
    expect(TwoFactor::requiredFor($this->owner))->toBeFalse()
        ->and(TwoFactor::requiredFor($this->admin))->toBeFalse()
        ->and(IdentitySettings::current()->require_two_factor)->toBeFalse();

    turnTwoFactorRule($this->owner, true);
    turnTwoFactorRule($this->owner, true);   // the same again writes nothing
    expect(TwoFactor::requiredFor($this->admin))->toBeTrue()
        ->and(DB::table('audit_logs')->where('subject_type', 'identity_settings')->orderBy('created_at')->pluck('action')->all())
        ->toBe(['identity.two_factor_optional', 'identity.two_factor_required']);
});

it('lets the back office in without setting it up while it is off, and still asks whoever has it on', function () {
    $setUp = 'http://noor.lms.test/admin/multi-factor-authentication/set-up';
    turnTwoFactorRule($this->owner, true);
    $this->clearTenant();
    $this->actingAs($this->admin)->get('http://noor.lms.test/admin/invoices')->assertRedirect($setUp);

    $this->actAsTenant($this->noor);
    turnTwoFactorRule($this->owner, false);
    $this->clearTenant();
    $this->actingAs($this->admin)->get('http://noor.lms.test/admin/invoices')->assertOk();
    // The set-up page itself sends them on.
    expect((string) $this->actingAs($this->admin)->get($setUp)->headers->get('Location'))->not->toContain('choose-password');
});

it('gives the apps a token while it is off, and asks for the set-up while it is on', function () {
    $login = fn () => $this->withHeaders(['X-Academy' => 'noor'])->postJson('/api/v1/auth/login', ['login' => 'amr@noor.test', 'password' => 'a-long-password', 'device_name' => 'Pixel 9']);
    $this->clearTenant();
    $login()->assertForbidden()->assertJsonPath('type', 'two-factor-setup-required');

    $this->actAsTenant($this->noor);
    turnTwoFactorRule($this->owner, false);
    $this->clearTenant();
    $login()->assertOk()->assertJsonStructure(['token']);

    // Whoever turned it on for themselves is still asked for it.
    $this->actAsTenant($this->noor);
    $this->admin->refresh()->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->saveQuietly();
    $this->clearTenant();
    $login()->assertForbidden()->assertJsonPath('type', 'two-factor-required');
});

it('is the owner\'s alone, and never while the platform team is signed in as someone', function () {
    $this->actingAs($this->admin);
    expect(fn () => app(SaveSecuritySettings::class)->handle(false))->toThrow(AuthorizationException::class)
        ->and(SecuritySettings::canAccess())->toBeFalse();

    $this->actingAs($this->owner);
    expect(SecuritySettings::canAccess())->toBeTrue();
    AuditActor::use(new AuditActor('platform', '0190f0a0-0000-7000-8000-0000000000aa', 'سلمى', $this->owner->id));
    expect(fn () => app(SaveSecuritySettings::class)->handle(false))->toThrow(ValidationException::class);
    AuditActor::forget();
    expect(TwoFactor::requiredFor($this->owner))->toBeTrue();

    // The screen: the switch, what it guards, and what turning it off means.
    $this->owner->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->saveQuietly();
    Filament::setCurrentPanel('academy');
    Livewire::test(SecuritySettings::class)
        ->assertSee(__('identity::security.why'))
        ->assertDontSee(__('identity::security.off'))
        ->set('data.require_two_factor', false)
        ->assertSee(__('identity::security.off'))
        ->call('save');
    expect(IdentitySettings::current()->require_two_factor)->toBeFalse();
    $this->clearTenant();
    $this->actingAs($this->admin)->get('http://noor.lms.test/admin/settings/security')->assertForbidden();
});
