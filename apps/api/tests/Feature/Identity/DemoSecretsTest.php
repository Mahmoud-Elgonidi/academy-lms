<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Audit\AuditActor;

/*
 * Rotating a server's demo secrets (infra/deploy/RUNBOOKS.md, "Changing a
 * secret"): once DEMO_PASSWORD and DEMO_PLATFORM_2FA_SECRET change in .env,
 * one command gives them to the demo accounts in every academy and to the demo
 * console team, and ends their sign-ins; a real account never changes.
 */

beforeEach(function () {
    $this->noor = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->demo = User::factory()->withRole(Role::Teacher)->create(['email' => 'khaled@demo.test', 'password' => 'the-old-demo-password']);
    $this->real = User::factory()->withRole(Role::Owner)->create(['email' => 'owner@noor.example', 'password' => 'the-old-demo-password']);
    $this->demo->createToken('phone');
    $this->real->createToken('phone');
    $this->clearTenant();
    $this->huda = $this->actAsTenant($this->createAcademy(['slug' => 'huda']));
    $this->other = User::factory()->withRole(Role::Student)->create(['email' => 'ziad@demo.test', 'password' => 'the-old-demo-password']);
    $this->clearTenant();

    $this->team = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'admin@platform.test', 'password' => 'the-old-demo-password']);
    $this->team->saveAppAuthenticationSecret('LMSDEMOPLATFORMCONSOLE2FASECRET2');
    $this->team->saveAppAuthenticationRecoveryCodes(['first-code', 'second-code']);
    $this->ops = PlatformAdmin::query()->create(['name' => 'Ops', 'email' => 'ops@company.example', 'password' => 'the-old-demo-password']);
    $this->ops->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP');

    config(['app.demo.password' => 'a-brand-new-demo-password', 'app.demo.platform_2fa_secret' => 'NEWDEMOKEYNEWDEMOKEY234567ABCDEF']);
});

afterEach(fn () => AuditActor::forget());

it('gives the demo accounts and the demo console team the new secrets and ends their sign-ins, never a real account', function () {
    $this->artisan('identity:demo-secrets')
        ->expectsOutputToContain('2 demo accounts and 1 demo console accounts')
        ->assertSuccessful();

    $this->actAsTenant($this->noor);
    expect(Hash::check('a-brand-new-demo-password', $this->demo->fresh()->password))->toBeTrue()
        ->and(Hash::check('the-old-demo-password', $this->real->fresh()->password))->toBeTrue()
        ->and($this->demo->fresh()->tokens()->count())->toBe(0)
        ->and($this->real->fresh()->tokens()->count())->toBe(1);
    $log = DB::table('audit_logs')->sole();
    expect($log->action)->toBe('identity.demo_passwords_reset')
        ->and(json_decode($log->data, true))->toBe(['count' => 1]);
    $this->clearTenant();

    $this->actAsTenant($this->huda);
    expect(Hash::check('a-brand-new-demo-password', $this->other->fresh()->password))->toBeTrue();
    $this->clearTenant();

    $team = $this->team->fresh();
    expect(Hash::check('a-brand-new-demo-password', $team->password))->toBeTrue()
        ->and($team->getAppAuthenticationSecret())->toBe('NEWDEMOKEYNEWDEMOKEY234567ABCDEF')
        ->and($team->getAppAuthenticationRecoveryCodes())->toBeNull()
        ->and(Hash::check('the-old-demo-password', $this->ops->fresh()->password))->toBeTrue()
        ->and($this->ops->fresh()->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP')
        ->and(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'platform.demo_secrets_reset')->pluck('subject_id')->all())->toBe([$this->team->id]);
});

it('keeps the console team\'s authenticator when the server names no demo key', function () {
    config(['app.demo.platform_2fa_secret' => null]);

    $this->artisan('identity:demo-secrets')->assertSuccessful();

    $team = $this->team->fresh();
    expect(Hash::check('a-brand-new-demo-password', $team->password))->toBeTrue()
        ->and($team->getAppAuthenticationSecret())->toBe('LMSDEMOPLATFORMCONSOLE2FASECRET2')
        ->and($team->getAppAuthenticationRecoveryCodes())->toBe(['first-code', 'second-code']);
});

it('refuses a short password or a key that is not base32 on a server, and changes nothing', function () {
    app()->detectEnvironment(fn () => 'production');
    try {
        config(['app.demo.password' => 'short-one']);
        $this->artisan('identity:demo-secrets')->expectsOutputToContain('12 characters or more')->assertFailed();

        config(['app.demo.password' => 'a-brand-new-demo-password', 'app.demo.platform_2fa_secret' => 'not base32 at all!']);
        $this->artisan('identity:demo-secrets')->expectsOutputToContain('base32')->assertFailed();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    $this->actAsTenant($this->noor);
    expect(Hash::check('the-old-demo-password', $this->demo->fresh()->password))->toBeTrue();
    $this->clearTenant();
    expect($this->team->fresh()->getAppAuthenticationSecret())->toBe('LMSDEMOPLATFORMCONSOLE2FASECRET2');
});
