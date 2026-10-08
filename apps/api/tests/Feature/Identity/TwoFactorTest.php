<?php

use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Identity\Actions\Impersonation\StartImpersonation;
use Modules\Identity\Actions\TwoFactor\ResetTwoFactor;
use Modules\Identity\Contracts\DTOs\OtpMessage;
use Modules\Identity\Contracts\OtpSender;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Identity\Enums\Role;
use Modules\Identity\Filament\Console\Pages\SupportSignIn;
use Modules\Identity\Filament\Pages\MySettings;
use Modules\Identity\Filament\Pages\SignIn;
use Modules\Identity\Models\User;
use Modules\Platform\Contracts\DTOs\PlatformAdminRef;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Audit\AuditActor;
use PragmaRX\Google2FA\Google2FA;

/*
 * Two-step sign-in (docs/architecture/01, section 1): an authenticator app
 * with recovery codes, required of the owner, admins and accountants, asked
 * on every way in of whoever has it on, and reset only by the platform team.
 */

final class TwoStepOtpSender implements OtpSender
{
    /** @var list<OtpMessage> */
    public array $sent = [];

    public function supports(string $channel): bool
    {
        return true;
    }

    public function send(OtpMessage $message): void
    {
        $this->sent[] = $message;
    }
}

beforeEach(function () {
    $this->withoutDefer();
    $this->sender = new TwoStepOtpSender;
    app()->instance(OtpSender::class, $this->sender);
    $this->noor = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->owner = User::factory()->withRole(Role::Owner, Role::Admin)->withoutTwoFactor()->create(['name' => 'هالة', 'email' => 'hala@noor.test', 'password' => 'a-long-password']);
    $this->accountant = User::factory()->withRole(Role::Accountant)->create(['name' => 'سامي', 'email' => 'sami@noor.test', 'password' => 'a-long-password']);
    $this->supervisor = User::factory()->withRole(Role::Supervisor)->create(['email' => 'sup@noor.test', 'password' => 'a-long-password']);
    $this->teacher = User::factory()->withRole(Role::Teacher)->withTwoFactor()->create(['email' => 'khaled@noor.test', 'phone_e164' => '+966500000009', 'password' => 'a-long-password']);
    $this->clearTenant();
});

afterEach(fn () => AuditActor::forget());

function totp(int $stepsAgo = 0): string
{
    $google = app(Google2FA::class);

    return $google->oathTotp(UserFactory::TWO_FACTOR_SECRET, $google->getTimestamp() - $stepsAgo);
}

it('sends the owner, admins and accountants to set it up first, and lets everyone else in', function () {
    $setUp = 'http://noor.lms.test/admin/multi-factor-authentication/set-up';
    $this->actingAs($this->owner)->get('http://noor.lms.test/admin/invoices')->assertRedirect($setUp);
    $this->actingAs($this->owner)->get($setUp)->assertOk();
    // Not asked of a supervisor; set up already by the accountant; the teacher area asks nobody to.
    expect((string) $this->actingAs($this->supervisor)->get('http://noor.lms.test/admin')->headers->get('Location'))->not->toContain('multi-factor');
    $this->actingAs($this->accountant)->get('http://noor.lms.test/admin/invoices')->assertOk();
    expect((string) $this->actingAs(tap($this->teacher)->forceFill(['app_authentication_secret' => null]))->get('http://noor.lms.test/teach')->headers->get('Location'))->not->toContain('multi-factor');
});

it('does not ask the platform team signed in as someone, and lets nobody change it then', function () {
    $ref = new PlatformAdminRef('0190f0a0-0000-7000-8000-0000000000aa', 'سلمى', 'salma@platform.test');
    PlatformAdmin::query()->forceCreate(['id' => $ref->id, 'name' => 'سلمى', 'email' => 'salma@platform.test', 'password' => 'a-long-password']);
    ['url' => $url] = app(StartImpersonation::class)->handle(app(TenantDirectory::class)->byId($this->noor->id), $this->owner->id, $ref, 'المالكة مش شايفة صفحة الفواتير.', 15);

    $this->get('http://noor.lms.test'.parse_url($url, PHP_URL_PATH))->assertRedirect();
    $this->get('http://noor.lms.test/admin/invoices')->assertOk();
    $this->get('http://noor.lms.test/admin/profile')->assertOk()->assertDontSee(__('filament-panels::auth/multi-factor/app/provider.management_schema.actions.label'));

    $this->actAsTenant($this->noor);
    AuditActor::use(new AuditActor('platform', $ref->id, $ref->name, $this->owner->id));
    expect(fn () => $this->owner->refresh()->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP'))->toThrow(AuthorizationException::class);
});

it('asks for the authenticator code after the password on the panels, and keeps a line when it is turned on or off', function () {
    $this->actAsTenant($this->noor);
    Filament::setCurrentPanel('teach');

    $page = Livewire::test(SignIn::class)
        ->fillForm(['email' => 'khaled@noor.test', 'password' => 'a-long-password'])
        ->call('authenticate')
        ->assertNotSet('userUndertakingMultiFactorAuthentication', null);
    expect(Auth::guard('web')->check())->toBeFalse();

    $page->set('data.multiFactor.app.code', '000000')->call('authenticate')->assertHasErrors();
    expect(Auth::guard('web')->check())->toBeFalse();
    $page->set('data.multiFactor.app.code', totp())->call('authenticate')->assertHasNoErrors();
    expect(Auth::guard('web')->id())->toBe($this->teacher->id);

    $this->teacher->refresh()->saveAppAuthenticationSecret(null);
    $this->teacher->refresh()->saveAppAuthenticationSecret(UserFactory::TWO_FACTOR_SECRET);
    expect(DB::table('audit_logs')->where('subject_id', $this->teacher->id)->orderBy('created_at')->pluck('action')->all())
        ->toBe(['identity.two_factor_disabled', 'identity.two_factor_enabled']);
});

it('asks for it after a one-time code too, within ten minutes of it', function () {
    $this->actAsTenant($this->noor);
    Filament::setCurrentPanel('teach');

    $page = Livewire::test(SignIn::class)
        ->call('mountAction', 'useCode')->call('callMountedAction')
        ->fillForm(['email' => 'khaled@noor.test'])->call('authenticate');
    $page->fillForm(['code' => $this->sender->sent[0]->code])->call('authenticate')
        ->assertNotSet('userUndertakingMultiFactorAuthentication', null)
        ->assertSee(__('filament-panels::auth/multi-factor/app/provider.login_form.code.label'));
    expect(Auth::guard('web')->check())->toBeFalse();

    $page->set('data.multiFactor.app.code', '123456')->call('authenticate')->assertHasErrors();
    $page->set('data.multiFactor.app.code', totp())->call('authenticate')->assertHasNoErrors();
    expect(Auth::guard('web')->id())->toBe($this->teacher->id);

});

it('starts again when the second step after a one-time code comes too late', function () {
    $this->actAsTenant($this->noor);
    Filament::setCurrentPanel('teach');
    $late = Livewire::test(SignIn::class)
        ->call('mountAction', 'useCode')->call('callMountedAction')
        ->fillForm(['email' => 'khaled@noor.test'])->call('authenticate');
    $this->travel(61)->seconds();
    $late->fillForm(['code' => $this->sender->sent[array_key_last($this->sender->sent)]->code])->call('authenticate')
        ->assertNotSet('userUndertakingMultiFactorAuthentication', null);
    $this->travel(11)->minutes();
    $late->set('data.multiFactor.app.code', totp())->call('authenticate')->assertHasErrors(['data.email'])
        ->assertSet('userUndertakingMultiFactorAuthentication', null);
    expect(Auth::guard('web')->check())->toBeFalse();
});

it('gives the apps a second step instead of a token, once, five tries, and takes a recovery code once', function () {
    $this->actAsTenant($this->noor);
    $this->teacher->refresh()->saveAppAuthenticationRecoveryCodes([Hash::make('aaaaaaaaaa-bbbbbbbbbb')]);
    $this->clearTenant();
    $login = fn () => $this->withHeaders(['X-Academy' => 'noor'])->postJson('/api/v1/auth/login', ['login' => 'khaled@noor.test', 'password' => 'a-long-password', 'device_name' => 'Pixel 9']);
    $second = fn (string $token, string $code) => $this->withHeaders(['X-Academy' => 'noor', 'Accept-Language' => 'en'])->postJson('/api/v1/auth/two-factor', ['two_factor_token' => $token, 'code' => $code]);

    $first = $login()->assertForbidden()->assertJsonPath('type', 'two-factor-required')->assertJsonStructure(['two_factor_token', 'expires_at' => ['utc', 'local', 'timezone', 'label']]);
    $token = $first->json('two_factor_token');
    $second($token, '000000')->assertUnprocessable()->assertJsonValidationErrors('code');
    $second($token, totp())->assertOk()->assertJsonPath('user.id', $this->teacher->id)->assertJsonStructure(['token']);
    $second($token, totp(1))->assertUnprocessable()->assertJsonValidationErrors('two_factor_token');     // once

    // Five wrong tries end it.
    $token = $login()->json('two_factor_token');
    foreach (range(1, 5) as $i) {
        $second($token, '00000'.$i)->assertUnprocessable();
    }
    Cache::flush();
    $second($token, totp())->assertUnprocessable()->assertJsonValidationErrors('two_factor_token');

    // A recovery code works once, and the log says so.
    $token = $login()->json('two_factor_token');
    $second($token, 'aaaaaaaaaa-bbbbbbbbbb')->assertOk();
    $token = $login()->json('two_factor_token');
    $second($token, 'aaaaaaaaaa-bbbbbbbbbb')->assertUnprocessable();
    $this->actAsTenant($this->noor);
    expect(DB::table('audit_logs')->where('action', 'identity.recovery_code_used')->where('subject_id', $this->teacher->id)->count())->toBe(1);
});

it('asks the apps for it after a one-time code as well, and tells an owner without it to set it up first', function () {
    $code = function (string $contact) {
        $sent = $this->withHeaders(['X-Academy' => 'noor'])->postJson('/api/v1/auth/code', ['contact' => $contact])->assertAccepted();

        return $this->withHeaders(['X-Academy' => 'noor'])->postJson('/api/v1/auth/code/verify', [
            'challenge_id' => $sent->json('challenge_id'), 'code' => $this->sender->sent[array_key_last($this->sender->sent)]->code, 'device_name' => 'Pixel 9',
        ]);
    };
    $token = $code('+966500000009')->assertForbidden()->assertJsonPath('type', 'two-factor-required')->json('two_factor_token');
    $this->withHeaders(['X-Academy' => 'noor'])->postJson('/api/v1/auth/two-factor', ['two_factor_token' => $token, 'code' => totp()])->assertOk();

    $this->withHeaders(['X-Academy' => 'noor'])->postJson('/api/v1/auth/login', ['login' => 'hala@noor.test', 'password' => 'a-long-password', 'device_name' => 'x'])
        ->assertForbidden()->assertJsonPath('type', 'two-factor-setup-required');
    // Someone without it, and not asked to have it, signs in as before.
    $this->withHeaders(['X-Academy' => 'noor'])->postJson('/api/v1/auth/login', ['login' => 'sup@noor.test', 'password' => 'a-long-password', 'device_name' => 'x'])->assertOk();
});

it('asks for it when the app signs in to an account a code found, in that academy only', function () {
    $this->createAcademy(['slug' => 'hira']);
    $sent = $this->postJson('/api/v1/accounts/code', ['contact' => '+966500000009'])->assertAccepted();
    $found = $this->postJson('/api/v1/accounts/code/verify', ['challenge_id' => $sent->json('challenge_id'), 'code' => $this->sender->sent[array_key_last($this->sender->sent)]->code])->assertOk();

    $token = $this->withHeaders(['X-Academy' => 'noor'])->postJson('/api/v1/accounts/sign-in', [
        'discovery_token' => $found->json('discovery_token'), 'user_id' => $this->teacher->id, 'device_name' => 'iPhone',
    ])->assertForbidden()->assertJsonPath('type', 'two-factor-required')->json('two_factor_token');

    // The step belongs to the academy it started in.
    $this->withHeaders(['X-Academy' => 'hira'])->postJson('/api/v1/auth/two-factor', ['two_factor_token' => $token, 'code' => totp()])
        ->assertUnprocessable()->assertJsonValidationErrors('two_factor_token');
    $this->withHeaders(['X-Academy' => 'noor'])->postJson('/api/v1/auth/two-factor', ['two_factor_token' => $token, 'code' => totp(), 'device_name' => 'iPhone'])
        ->assertOk()->assertJsonPath('user.id', $this->teacher->id);
    $this->actAsTenant($this->noor);
    expect(DB::table('personal_access_tokens')->where('tokenable_id', $this->teacher->id)->pluck('name')->all())->toBe(['iPhone']);
});

it('shows it in My settings, and lets the platform team reset it with a reason', function () {
    $this->actAsTenant($this->noor);
    $this->actingAs($this->accountant);
    Filament::setCurrentPanel('academy');
    Livewire::test(MySettings::class)->assertSee(__('filament-panels::auth/multi-factor/app/provider.management_schema.actions.label'));
    $this->clearTenant();

    $admin = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@platform.test', 'password' => 'a-long-password']);
    $academy = app(TenantDirectory::class)->byId($this->noor->id);
    $ref = new PlatformAdminRef($admin->id, $admin->name, $admin->email);
    expect(fn () => app(ResetTwoFactor::class)->handle($academy, $this->accountant->id, $ref, 'ضاع'))->toThrow(ValidationException::class);

    Auth::guard('web')->forgetUser();
    $this->actingAs($admin, 'platform');
    Filament::setCurrentPanel('console');
    Livewire::test(SupportSignIn::class)
        ->set('academy', $this->noor->id)->call('choose', $this->accountant->id)
        ->assertSee(__('identity::two_factor.console.on'))
        ->set('reason', 'سامي ضيّع موبايله وأكواد الاسترجاع، واتأكدنا منه بالتليفون.')
        ->call('resetTwoFactor')->assertHasNoErrors()
        ->assertDontSee(__('identity::two_factor.console.on'));

    $this->actAsTenant($this->noor);
    expect($this->accountant->refresh()->getAppAuthenticationSecret())->toBeNull();
    $row = DB::table('audit_logs')->where('action', 'identity.two_factor_reset')->sole();
    expect($row->actor_type)->toBe('platform')->and($row->actor_name)->toBe('سلمى')
        ->and(json_decode($row->data, true)['reason'])->toContain('ضيّع موبايله');
    expect(fn () => app(ResetTwoFactor::class)->handle($academy, $this->accountant->id, $ref, 'سامي ضيّع موبايله تاني يا جماعة.'))->toThrow(ValidationException::class);
});
