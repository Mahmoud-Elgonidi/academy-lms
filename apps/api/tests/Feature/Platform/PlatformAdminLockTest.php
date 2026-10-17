<?php

use Carbon\CarbonImmutable;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Identity\Actions\Impersonation\StartImpersonation;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\Impersonation;
use Modules\Identity\Models\User;
use Modules\Platform\Contracts\DTOs\PlatformAdminRef;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Audit\AuditActor;
use Symfony\Component\Console\Tester\CommandTester;

/*
 * Locking someone of the platform team out (platform:admin --deactivate):
 * the demo's admin@platform.test before real academies arrive, or someone
 * who left. Locked, they cannot sign in, the session they had ends, a
 * sign-in as someone of an academy they started ends, and it is audited;
 * --reactivate lets them back. The console never loses its last admin.
 */

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-05 10:00:00');
    $this->noor = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->accountant = User::factory()->withRole(Role::Accountant)->create(['name' => 'المحاسب']);
    $this->clearTenant();
    $this->demo = PlatformAdmin::query()->create(['name' => 'Demo', 'email' => 'admin@platform.test', 'password' => 'a-long-password']);
    $this->demo->saveAppAuthenticationSecret('LMSDEMOPLATFORMCONSOLE2FASECRET2');
    $this->real = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@company.example', 'password' => 'a-long-password']);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    AuditActor::forget();
});

function consoleSignIn(string $email): Testable
{
    Filament::setCurrentPanel('console');

    return Livewire::test(Login::class)->fillForm(['email' => $email, 'password' => 'a-long-password'])->call('authenticate');
}

it('locks an admin out: no sign-in, audited, and back in with --reactivate', function () {
    $token = $this->demo->fresh()->getRememberToken();

    $this->artisan('platform:admin', ['email' => 'ADMIN@platform.test', '--deactivate' => true])->assertSuccessful();

    $locked = $this->demo->fresh();
    expect($locked->is_active)->toBeFalse()
        ->and($locked->getRememberToken())->not->toBe($token);
    consoleSignIn('admin@platform.test')->assertHasErrors(['data.email']);
    expect(Auth::guard('platform')->check())->toBeFalse();

    $audit = DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'platform.admin_deactivated')->sole();
    expect($audit->academy_id)->toBeNull()->and($audit->subject_id)->toBe($this->demo->id)
        ->and((string) $audit->data)->not->toContain('password');

    $this->artisan('platform:admin', ['email' => 'admin@platform.test', '--reactivate' => true])->assertSuccessful();
    expect($this->demo->fresh()->is_active)->toBeTrue()
        ->and(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'platform.admin_reactivated')->count())->toBe(1);
    consoleSignIn('admin@platform.test')->assertHasNoErrors()->assertNotSet('userUndertakingMultiFactorAuthentication', null);
});

it('ends the session a locked admin still has', function () {
    $this->actingAs($this->demo, 'platform')->get('/console/academies')->assertOk();

    $this->artisan('platform:admin', ['email' => 'admin@platform.test', '--deactivate' => true])->assertSuccessful();

    // The next request reads the admin afresh from the session, as a browser's would.
    Auth::guard('platform')->setUser($this->demo->fresh());
    $this->get('/console/academies')->assertRedirect('/console/login');
    expect(Auth::guard('platform')->check())->toBeFalse();
});

it('ends a sign-in as someone of an academy that a locked admin started, and refuses its link', function () {
    $academy = app(TenantDirectory::class)->byId($this->noor->id);
    $ref = new PlatformAdminRef($this->demo->id, $this->demo->name, $this->demo->email);
    $reason = 'المحاسب مش شايف كشف المرتبات، عايزين نشوف اللي هو شايفه.';
    ['url' => $url, 'impersonation' => $impersonation] = app(StartImpersonation::class)->handle($academy, $this->accountant->id, $ref, $reason, 15);
    $this->get('http://noor.lms.test'.parse_url($url, PHP_URL_PATH))->assertRedirect('http://noor.lms.test/admin');
    $this->get('http://noor.lms.test/admin/payroll')->assertOk();
    ['url' => $unused] = app(StartImpersonation::class)->handle($academy, $this->accountant->id, $ref, $reason, 15);

    $this->artisan('platform:admin', ['email' => 'admin@platform.test', '--deactivate' => true])->assertSuccessful();

    $this->get('http://noor.lms.test/admin/payroll')->assertRedirect(route('identity.impersonate.ended', ['why' => 'ended']));
    expect(Auth::guard('web')->check())->toBeFalse();
    $this->actAsTenant($this->noor);
    expect(Impersonation::query()->find($impersonation->id)->ended_reason)->toBe('ended');
    $this->clearTenant();
    $this->get('http://noor.lms.test'.parse_url($unused, PHP_URL_PATH))->assertStatus(410);
    expect(Auth::guard('web')->check())->toBeFalse();
});

it('never locks the last admin who can sign in, and says who it does not know', function () {
    $this->artisan('platform:admin', ['email' => 'salma@company.example', '--deactivate' => true])->assertSuccessful();
    $this->artisan('platform:admin', ['email' => 'admin@platform.test', '--deactivate' => true])->assertFailed();
    expect($this->demo->fresh()->is_active)->toBeTrue();

    $this->artisan('platform:admin', ['email' => 'nobody@company.example', '--deactivate' => true])->assertFailed();
    $this->artisan('platform:admin', ['email' => 'admin@platform.test', '--deactivate' => true, '--reactivate' => true])->assertFailed();
});

it('still adds a new admin, who needs a name', function () {
    $this->artisan('platform:admin', ['email' => 'new@company.example', 'name' => 'Nada', '--password' => 'a-long-password'])->assertSuccessful();
    expect(PlatformAdmin::query()->where('email', 'new@company.example')->value('is_active'))->toBeTrue();
    $this->artisan('platform:admin', ['email' => 'other@company.example', '--password' => 'a-long-password'])->assertFailed();
});

it('adds an owner whose password comes on stdin, as infra/deploy/console.sh sends it', function () {
    $run = function (string $email, array $stdin): int {
        $tester = new CommandTester(Artisan::all()['platform:admin']);
        $tester->setInputs($stdin);

        return $tester->execute(['email' => $email, 'name' => 'Mahmoud', '--owner' => true, '--password-stdin' => true], ['interactive' => false]);
    };

    expect($run('new@company.example', ['a-long-password-from-stdin']))->toBe(0);
    $admin = PlatformAdmin::query()->where('email', 'new@company.example')->firstOrFail();
    expect($admin)->is_owner->toBeTrue()->is_active->toBeTrue()->name->toBe('Mahmoud')
        ->and(Hash::check('a-long-password-from-stdin', $admin->password))->toBeTrue();

    // Nothing on stdin, or too short: nobody is added.
    expect($run('empty@company.example', []))->toBe(1)
        ->and($run('short@company.example', ['short']))->toBe(1)
        ->and(PlatformAdmin::query()->whereIn('email', ['empty@company.example', 'short@company.example'])->exists())->toBeFalse();
});

it('makes an admin an owner and back, audited, and never takes the last owner who can sign in', function () {
    $this->artisan('platform:admin', ['email' => 'salma@company.example', '--owner' => true])->assertSuccessful();
    expect($this->real->fresh()->is_owner)->toBeTrue()
        ->and(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'platform.admin_made_owner')->value('subject_id'))->toBe($this->real->id);

    // The last owner stays one, and is not locked out.
    $this->artisan('platform:admin', ['email' => 'salma@company.example', '--no-owner' => true])->assertFailed();
    $this->artisan('platform:admin', ['email' => 'salma@company.example', '--deactivate' => true])->assertFailed();
    expect($this->real->fresh())->is_owner->toBeTrue()->is_active->toBeTrue();

    // With a second owner, either may go.
    $this->artisan('platform:admin', ['email' => 'admin@platform.test', '--owner' => true])->assertSuccessful();
    $this->artisan('platform:admin', ['email' => 'admin@platform.test', '--deactivate' => true])->assertSuccessful();
    // A locked owner does not count: Salma is still the last one who can sign in.
    $this->artisan('platform:admin', ['email' => 'salma@company.example', '--no-owner' => true])->assertFailed();
    $this->artisan('platform:admin', ['email' => 'admin@platform.test', '--reactivate' => true])->assertSuccessful();
    $this->artisan('platform:admin', ['email' => 'salma@company.example', '--no-owner' => true])->assertSuccessful();
    expect($this->real->fresh()->is_owner)->toBeFalse()
        ->and(DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'platform.admin_owner_removed')->count())->toBe(1);

    $this->artisan('platform:admin', ['email' => 'salma@company.example', '--owner' => true, '--no-owner' => true])->assertFailed();
    $this->artisan('platform:admin', ['email' => 'nobody@company.example', '--no-owner' => true])->assertFailed();
});

it('never makes a locked admin an owner', function () {
    $this->artisan('platform:admin', ['email' => 'admin@platform.test', '--deactivate' => true])->assertSuccessful();
    $this->artisan('platform:admin', ['email' => 'admin@platform.test', '--owner' => true])->assertFailed();
    expect($this->demo->fresh()->is_owner)->toBeFalse();
});

it('makes the first admin of a team with no owner one, and others only when asked', function () {
    $this->artisan('platform:admin', ['email' => 'first@company.example', 'name' => 'First', '--password' => 'a-long-password'])->assertSuccessful();
    $this->artisan('platform:admin', ['email' => 'second@company.example', 'name' => 'Second', '--password' => 'a-long-password'])->assertSuccessful();
    $this->artisan('platform:admin', ['email' => 'third@company.example', 'name' => 'Third', '--password' => 'a-long-password', '--owner' => true])->assertSuccessful();

    expect(PlatformAdmin::query()->where('is_owner', true)->orderBy('email')->pluck('email')->all())->toBe(['first@company.example', 'third@company.example']);
});

it('makes the earliest admin who can sign in the first owner when the column comes', function () {
    $old = PlatformAdmin::query()->create(['name' => 'Gone', 'email' => 'gone@company.example', 'password' => 'a-long-password', 'is_active' => false]);
    DB::connection('pgsql_platform')->table('platform_admins')->where('id', $old->id)->update(['created_at' => now()->subYear()]);
    DB::connection('pgsql_platform')->table('platform_admins')->where('id', $this->demo->id)->update(['created_at' => now()->subMonth()]);

    // As the deploy runs it: on the owner's connection, the table's row-level security forced with no policy.
    $migration = require base_path('modules/Platform/database/migrations/2027_03_03_100000_add_owner_to_platform_admins.php');
    $default = DB::getDefaultConnection();
    DB::setDefaultConnection('pgsql_owner');
    try {
        $migration->down();
        $migration->up();
    } finally {
        DB::setDefaultConnection($default);
    }

    expect(PlatformAdmin::query()->where('is_owner', true)->pluck('email')->all())->toBe(['admin@platform.test'])
        ->and(DB::connection('pgsql_owner')->selectOne("select relforcerowsecurity as forced from pg_class where relname = 'platform_admins'")->forced)->toBeTrue();
});
