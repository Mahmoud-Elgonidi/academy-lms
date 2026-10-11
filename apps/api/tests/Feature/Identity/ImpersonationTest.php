<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Identity\Actions\Impersonation\EndImpersonation;
use Modules\Identity\Actions\Impersonation\StartImpersonation;
use Modules\Identity\Actions\UpdateUserDetails;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\Identity\Enums\Role;
use Modules\Identity\Filament\Console\Pages\SupportSignIn;
use Modules\Identity\Filament\Pages\ActivityLog;
use Modules\Identity\Filament\Pages\MySettings;
use Modules\Identity\Models\Impersonation;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\SetAcademyStatus;
use Modules\Platform\Contracts\DTOs\PlatformAdminRef;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Audit\AuditActor;

/*
 * The platform team signing in as someone of an academy (docs/architecture/01:
 * "الدخول بحساب مستخدم مع تسجيله"): a reason and a time limit, a one-time
 * link on the academy's own host, a bar on every page, no password changes,
 * an end on exit, on time or from the console, and every step in the
 * academy's log and the person's settings.
 */

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-05 10:00:00');
    $this->noor = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->owner = User::factory()->withRole(Role::Owner, Role::Admin)->create(['name' => 'هالة']);
    $this->accountant = User::factory()->withRole(Role::Accountant)->create(['name' => 'المحاسب', 'locale' => 'ar']);
    $this->clearTenant();
    $this->huda = $this->createAcademy(['slug' => 'huda']);
    $this->admin = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@platform.test', 'password' => 'a-long-password']);
    $this->ref = new PlatformAdminRef($this->admin->id, $this->admin->name, $this->admin->email);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    AuditActor::forget();
});

function startSignIn(?User $as = null, int $minutes = 15): array
{
    $t = test();

    return app(StartImpersonation::class)->handle(app(TenantDirectory::class)->byId($t->noor->id), ($as ?? $t->accountant)->id, $t->ref, 'المحاسب مش شايف كشف المرتبات، عايزين نشوف اللي هو شايفه.', $minutes);
}

function pathOf(string $url): string
{
    return 'http://noor.lms.test'.parse_url($url, PHP_URL_PATH);
}

it('asks for a clear reason, a set time and an active person', function () {
    $academy = app(TenantDirectory::class)->byId($this->noor->id);
    $start = fn (string $reason, int $minutes, string $user) => app(StartImpersonation::class)->handle($academy, $user, $this->ref, $reason, $minutes);

    expect(fn () => $start('مشكلة', 15, $this->accountant->id))->toThrow(ValidationException::class)
        ->and(fn () => $start('مشكلة في الفواتير عند ولي الأمر', 240, $this->accountant->id))->toThrow(ValidationException::class)
        ->and(fn () => $start('مشكلة في الفواتير عند ولي الأمر', 15, '0190f0a0-0000-7000-8000-000000000009'))->toThrow(ValidationException::class);

    AuditActor::use(new AuditActor('platform', $this->admin->id, $this->admin->name));
    app(SetAcademyStatus::class)->suspend($this->noor->id, 'متأخر في الدفع');
    expect(fn () => startSignIn())->toThrow(ValidationException::class);
});

it('makes a one-time link on the academy\'s host, keeping only its hash, and says so in the academy\'s log', function () {
    ['impersonation' => $impersonation, 'url' => $url] = startSignIn();

    $token = basename(parse_url($url, PHP_URL_PATH));
    expect($url)->toContain('://noor.lms.test')
        ->and($impersonation->token_hash)->toBe(hash('sha256', $token))
        ->and($impersonation->redeem_by->equalTo(CarbonImmutable::now()->addMinutes(2)))->toBeTrue();

    $this->actAsTenant($this->noor);
    $row = DB::table('audit_logs')->where('action', 'impersonation.started')->sole();
    expect($row->actor_type)->toBe('platform')
        ->and($row->actor_name)->toBe('سلمى')
        ->and($row->on_behalf_of)->toBe($this->accountant->id)
        ->and($row->impersonation_id)->toBe($impersonation->id)
        ->and(json_decode($row->data, true))->toMatchArray(['minutes' => 15, 'user' => 'المحاسب']);
});

it('signs in as the person once, opens their own area under a bar, and refuses the link again', function () {
    ['url' => $url] = startSignIn();

    $this->get(pathOf($url))->assertRedirect('http://noor.lms.test/admin');
    expect(Auth::guard('web')->id())->toBe($this->accountant->id);
    $this->get('http://noor.lms.test/admin/payroll')->assertOk()->assertSee('سلمى من فريق المنصة داخل بحساب المحاسب')->assertSee(route('identity.impersonate.exit'), false);

    $this->get(pathOf($url))->assertStatus(410);
});

it('works only on its own academy\'s host, and only for two minutes', function () {
    ['url' => $url] = startSignIn();
    $path = parse_url($url, PHP_URL_PATH);
    $this->get('http://huda.lms.test'.$path)->assertStatus(410);

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(3));
    $this->get('http://noor.lms.test'.$path)->assertStatus(410);
    expect(Auth::guard('web')->check())->toBeFalse();
});

it('does what is done as the platform team on the person\'s behalf, and blocks their password', function () {
    ['url' => $url, 'impersonation' => $impersonation] = startSignIn();
    $this->get(pathOf($url));
    $this->get('http://noor.lms.test/admin/payroll')->assertOk();

    expect(AuditActor::current())->toEqual(new AuditActor('platform', $this->admin->id, 'سلمى', $this->accountant->id, $impersonation->id));

    Filament::setCurrentPanel('academy');
    $this->actAsTenant($this->noor);
    Livewire::test(MySettings::class)->assertDontSee(__('identity::settings.sections.password'));
});

it('ends on exit: signs out, keeps the line, and points back to the console', function () {
    ['url' => $url, 'impersonation' => $impersonation] = startSignIn();
    $this->get(pathOf($url));

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(4));
    $this->post('http://noor.lms.test/impersonate/exit')->assertRedirect(route('identity.impersonate.ended', ['why' => 'exit']));
    expect(Auth::guard('web')->check())->toBeFalse();

    $this->actAsTenant($this->noor);
    $fresh = Impersonation::query()->find($impersonation->id);
    expect($fresh->ended_reason)->toBe('exit')
        ->and($fresh->ended_at->equalTo(CarbonImmutable::now()))->toBeTrue()
        ->and(DB::table('audit_logs')->where('action', 'impersonation.ended')->value('data'))->toContain('"how": "exit"');
    $this->clearTenant();

    $this->get('http://noor.lms.test/impersonate/ended?why=exit', ['Accept-Language' => 'ar'])->assertOk()->assertSee('خرجت من الحساب')->assertSee('/console/support-sign-in');
});

it('ends by itself when its time is up', function () {
    ['url' => $url, 'impersonation' => $impersonation] = startSignIn(minutes: 15);
    $this->get(pathOf($url));

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(16));
    $this->get('http://noor.lms.test/admin/payroll')->assertRedirect(route('identity.impersonate.ended', ['why' => 'expired']));
    expect(Auth::guard('web')->check())->toBeFalse();

    $this->actAsTenant($this->noor);
    $fresh = Impersonation::query()->find($impersonation->id);
    expect($fresh->ended_reason)->toBe('expired')
        ->and($fresh->ended_at->equalTo($fresh->expires_at))->toBeTrue();
});

it('ends from the console: the next step of that session signs out', function () {
    ['url' => $url, 'impersonation' => $impersonation] = startSignIn();
    $this->get(pathOf($url));

    $this->actAsTenant($this->noor);
    app(EndImpersonation::class)->handle($impersonation, 'ended');
    app(EndImpersonation::class)->handle($impersonation, 'exit'); // twice does nothing
    $this->clearTenant();

    $this->get('http://noor.lms.test/admin/payroll')->assertRedirect(route('identity.impersonate.ended', ['why' => 'ended']));
    $this->actAsTenant($this->noor);
    expect(Impersonation::query()->find($impersonation->id)->ended_reason)->toBe('ended')
        ->and(DB::table('audit_logs')->where('action', 'impersonation.ended')->count())->toBe(1);
});

it('starts and ends sign-ins from the console screen', function () {
    Filament::setCurrentPanel('console');
    $this->actingAs($this->admin, 'platform');

    $page = Livewire::test(SupportSignIn::class)
        ->set('academy', $this->noor->id)
        ->set('search', 'المحاسب')
        ->assertSee('المحاسب')
        ->call('choose', $this->accountant->id)
        ->set('reason', 'قصير')->call('start')->assertHasErrors(['reason'])
        ->set('reason', 'المحاسب مش شايف كشف المرتبات بتاع الشهر ده.')->set('minutes', 30)->call('start')->assertHasNoErrors();
    expect($page->get('ready.url'))->toMatch('#://noor\.lms\.test(:\d+)?/impersonate/#');

    $this->actAsTenant($this->noor);
    $impersonation = Impersonation::query()->sole();
    expect($impersonation->minutes)->toBe(30)->and($impersonation->admin_name)->toBe('سلمى');
    $this->clearTenant();

    $page->call('end', $impersonation->id);
    $this->actAsTenant($this->noor);
    expect(Impersonation::query()->sole()->ended_reason)->toBe('ended');
});

it('lists the sign-ins to their account in the person\'s own settings', function () {
    ['url' => $url] = startSignIn();
    $this->get(pathOf($url));
    $this->post('http://noor.lms.test/impersonate/exit');

    $this->actAsTenant($this->noor);
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->accountant);
    Livewire::test(MySettings::class)
        ->assertSee(__('identity::impersonation.mine.title'))
        ->assertSee('المحاسب مش شايف كشف المرتبات');

    $this->actingAs($this->owner);
    Livewire::test(MySettings::class)->assertDontSee(__('identity::impersonation.mine.title'));
});

it('shows the academy\'s activity to its owner and admins, the platform team\'s sign-ins included', function () {
    ['url' => $url] = startSignIn();
    $this->actAsTenant($this->noor);
    app()->setLocale('ar');
    Filament::setCurrentPanel('academy');

    $this->actingAs($this->owner);
    $page = Livewire::test(ActivityLog::class);
    expect($page->instance()->feed['rows'][0])->toMatchArray([
        'actor' => 'فريق المنصة · سلمى، بحساب المحاسب',
        'platform' => true,
        'group' => 'impersonation',
    ]);
    $page->set('who', 'user');
    expect($page->instance()->feed['rows'])->toBe([]);

    $this->actingAs($this->accountant)->get('http://noor.lms.test/admin/settings/activity')->assertForbidden();
});

it('never changes anyone\'s email or number while signed in as a staff member, which would open another account to a code', function () {
    ['url' => $url, 'impersonation' => $impersonation] = startSignIn($this->owner);
    $this->get(pathOf($url));
    $this->actAsTenant($this->noor);
    AuditActor::use(new AuditActor('platform', $this->admin->id, $this->admin->name, $this->owner->id, $impersonation->id));
    $before = $this->accountant->only(['email', 'phone_e164']);

    expect(fn () => app(UpdateUserDetails::class)->handle($this->accountant->id, new UserDetails('المحاسب', 'someone-else@company.example', $this->accountant->phone_e164)))
        ->toThrow(ValidationException::class);
    expect(fn () => app(UpdateUserDetails::class)->handle($this->accountant->id, new UserDetails('المحاسب', $this->accountant->email, '+966500000077')))
        ->toThrow(ValidationException::class);
    expect($this->accountant->fresh()->only(['email', 'phone_e164']))->toBe($before);

    // Other details are still the staff's to put right.
    app(UpdateUserDetails::class)->handle($this->accountant->id, new UserDetails('المحاسب الأول', $this->accountant->email, $this->accountant->phone_e164));
    expect($this->accountant->fresh()->name)->toBe('المحاسب الأول');
});
