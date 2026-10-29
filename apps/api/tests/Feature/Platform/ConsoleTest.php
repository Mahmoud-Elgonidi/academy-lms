<?php

use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Identity\Actions\OnboardAcademy;
use Modules\Identity\Enums\Role;
use Modules\Identity\Filament\Console\Pages\NewAcademy;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\SetAcademyStatus;
use Modules\Platform\Contracts\DTOs\NewAcademy as NewAcademyData;
use Modules\Platform\Enums\AcademyStatus;
use Modules\Platform\Filament\Console\Pages\Academies;
use Modules\Platform\Filament\Console\Pages\AcademyOverview;
use Modules\Platform\Filament\Console\Pages\AuditLog;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Audit\AuditActor;

/*
 * The platform team's console (docs/architecture/01: "لوحة Super Admin:
 * الأكاديميات، والاستخدام، وإيقاف أكاديمية"): its own people and sign-in,
 * an authenticator app required, the academies with their use, suspending
 * one, a new academy with its owner, and the audit log across them.
 */

beforeEach(function () {
    $this->noor = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'name_ar' => 'أكاديمية النور', 'name_en' => 'Noor']));
    $this->owner = User::factory()->withRole(Role::Owner, Role::Admin)->create(['name' => 'هالة', 'email' => 'owner@noor.test']);
    User::factory()->withRole(Role::Teacher)->count(2)->create();
    User::factory()->withRole(Role::Student)->count(3)->create();
    $this->clearTenant();
    $this->admin = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@platform.test', 'password' => 'a-long-password', 'timezone' => 'Africa/Cairo']);
    $this->admin->saveAppAuthenticationSecret('LMSDEMOPLATFORMCONSOLE2FASECRET2');
});

afterEach(fn () => AuditActor::forget());

it('keeps the platform team in a table the academies cannot read', function () {
    $this->actAsTenant($this->noor);

    expect(DB::table('platform_admins')->count())->toBe(0)
        ->and(DB::connection('pgsql_platform')->table('platform_admins')->count())->toBe(1);
});

it('lets in only the platform team, with their authenticator app set up', function () {
    $this->get('/console/academies')->assertRedirect('/console/login');
    $this->actingAs($this->owner)->get('/console/academies')->assertRedirect('/console/login');

    $this->actingAs($this->admin, 'platform')->get('/console/academies')->assertOk()->assertSee('أكاديمية النور');

    $new = PlatformAdmin::query()->create(['name' => 'جديد', 'email' => 'new@platform.test', 'password' => 'a-long-password'])->fresh();
    $this->flushSession();
    $this->actingAs($new, 'platform')->get('/console/academies')->assertRedirectContains('/console/multi-factor-authentication/set-up');
});

it('notes each console sign-in in the platform\'s log', function () {
    event(new Login('platform', $this->admin, false));

    $row = DB::connection('pgsql_platform')->table('audit_logs')->sole();
    expect($row->academy_id)->toBeNull()
        ->and($row->action)->toBe('platform.signed_in')
        ->and($row->actor_type)->toBe('platform')
        ->and($this->admin->fresh()->last_login_at)->not->toBeNull();
});

it('lists the academies with their people and use', function () {
    Filament::setCurrentPanel('console');
    $this->actingAs($this->admin, 'platform');
    app()->setLocale('ar');

    $page = Livewire::test(Academies::class)->assertSee('أكاديمية النور');
    $row = collect($page->instance()->academies)->firstWhere('id', $this->noor->id);
    expect($row['usage'])->toMatchArray(['teachers' => 2, 'students' => 3, 'staff' => 1, 'lessons' => 0]);

    $page->set('q', 'nothing-like-this')->assertSee(__('platform::console.academies.empty'));
});

it('suspends an academy with a reason, which stops its site and app, and brings it back', function () {
    Filament::setCurrentPanel('console');
    $this->actingAs($this->admin, 'platform');
    AuditActor::use(new AuditActor('platform', $this->admin->id, $this->admin->name));

    Livewire::test(AcademyOverview::class, ['academy' => $this->noor->id])
        ->callAction('suspend', data: ['reason' => 'لا'])->assertHasActionErrors(['reason']);
    expect(fn () => app(SetAcademyStatus::class)->suspend($this->noor->id, ' ok '))->toThrow(ValidationException::class);

    Livewire::test(AcademyOverview::class, ['academy' => $this->noor->id])
        ->callAction('suspend', data: ['reason' => 'الاشتراك متأخر شهرين'])->assertHasNoActionErrors();
    expect(Academy::query()->find($this->noor->id)->status)->toBe(AcademyStatus::Suspended);

    $this->get('http://noor.lms.test/admin/login')->assertStatus(503)->assertSee(__('shared::problems.suspended_page.title'));
    $this->getJson('http://noor.lms.test/api/v1/branding')->assertForbidden()->assertJsonPath('type', 'academy-suspended');

    // The academy's own log holds it too, by the platform team.
    $this->actAsTenant($this->noor);
    $row = DB::table('audit_logs')->where('action', 'academy.suspended')->sole();
    expect($row->actor_type)->toBe('platform')->and(json_decode($row->data, true)['reason'])->toBe('الاشتراك متأخر شهرين');
    $this->clearTenant();

    app(SetAcademyStatus::class)->reactivate($this->noor->id);
    $this->get('http://noor.lms.test/admin/login')->assertOk();
});

it('makes a new academy with its owner, who gets a link to choose a password', function () {
    AuditActor::use(new AuditActor('platform', $this->admin->id, $this->admin->name));
    $result = app(OnboardAcademy::class)->handle(
        new NewAcademyData(slug: 'rawda', nameAr: 'الروضة', nameEn: 'Rawda', baseCurrency: 'EGP', defaultTimezone: 'Africa/Cairo'),
        'محمود', 'Mahmoud@Rawda.test', '+201012345678',
    );

    $this->actAsTenant($result['academy']);
    $owner = User::query()->sole();
    expect($owner->email)->toBe('mahmoud@rawda.test')
        ->and($owner->password)->toBeNull()
        ->and(array_map(fn (Role $r) => $r->value, $owner->roles()))->toBe(['owner', 'admin'])
        ->and($owner->timezone)->toBe('Africa/Cairo')
        ->and(DB::table('audit_logs')->where('action', 'academy.created')->value('actor_type'))->toBe('platform')
        ->and($result['link'])->toContain('://rawda.lms.test')->toContain('/welcome/'.$owner->id.'?expires=');
    $this->clearTenant();

    $this->get($result['link'])->assertOk()->assertSee('mahmoud@rawda.test');
});

it('checks the owner before making the academy, so none is left without one', function () {
    expect(fn () => app(OnboardAcademy::class)->handle(
        new NewAcademyData(slug: 'rawda', nameAr: 'الروضة', nameEn: 'Rawda', baseCurrency: 'EGP'),
        'محمود', 'not-an-email',
    ))->toThrow(ValidationException::class)
        ->and(Academy::query()->where('slug', 'rawda')->exists())->toBeFalse();

    expect(fn () => app(OnboardAcademy::class)->handle(
        new NewAcademyData(slug: 'noor', nameAr: 'نور', nameEn: 'Noor', baseCurrency: 'SAR'),
        'محمود', 'm@x.test',
    ))->toThrow(ValidationException::class);
});

it('makes the academy from the console screen and shows the owner\'s link', function () {
    Filament::setCurrentPanel('console');
    $this->actingAs($this->admin, 'platform');

    Livewire::test(NewAcademy::class)
        ->fillForm(['name_ar' => 'الروضة', 'name_en' => 'Rawda', 'slug' => 'rawda', 'base_currency' => 'EGP', 'default_timezone' => 'Africa/Cairo', 'default_locale' => 'ar', 'country' => 'eg', 'owner_name' => 'محمود', 'owner_email' => 'm@rawda.test'])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertSee('rawda.lms.test')->assertSee('/welcome/');

    expect(Academy::query()->where('slug', 'rawda')->value('country'))->toBe('EG');
});

it('shows the audit log across academies, filtered by academy or the platform\'s own', function () {
    event(new Login('platform', $this->admin, false));
    AuditActor::use(new AuditActor('platform', $this->admin->id, $this->admin->name));
    app(SetAcademyStatus::class)->suspend($this->noor->id, 'الاشتراك متأخر');
    Filament::setCurrentPanel('console');
    $this->actingAs($this->admin, 'platform');
    app()->setLocale('ar');

    $page = Livewire::test(AuditLog::class);
    expect(array_column($page->instance()->feed['rows'], 'title'))->toBe(['إيقاف الأكاديمية: الاشتراك متأخر', 'دخول إلى لوحة المنصة']);
    $page->set('academy', 'platform');
    expect(array_column($page->instance()->feed['rows'], 'title'))->toBe(['دخول إلى لوحة المنصة']);
    $page->set('academy', $this->noor->id)->set('group', 'billing');
    expect($page->instance()->feed['rows'])->toBe([]);
});

it('prints an admin\'s console code on a server only for the demo admin, whose key the server holds', function () {
    $key = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
    $this->admin->saveAppAuthenticationSecret($key);
    $this->artisan('platform:code salma@platform.test')->assertSuccessful();   // local development and tests: anyone's

    app()->detectEnvironment(fn () => 'production');
    try {
        config(['app.demo.platform_2fa_secret' => null]);
        $this->artisan('platform:code salma@platform.test')->expectsOutputToContain('Only in local development')->assertFailed();
        config(['app.demo.platform_2fa_secret' => 'ANOTHERKEYANOTHERKEYANOTHERKEY23']);
        $this->artisan('platform:code salma@platform.test')->assertFailed();
        config(['app.demo.platform_2fa_secret' => $key]);
        $this->artisan('platform:code salma@platform.test')->assertSuccessful();
        $this->artisan('platform:code nobody@platform.test')->expectsOutputToContain('No such admin')->assertFailed();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
});
