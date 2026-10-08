<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Billing\Actions\Payments\SaveGatewayAccount;
use Modules\Billing\Actions\Payments\StartCheckout;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Payments\OnlinePayments;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Crm\Filament\Pages\Leads;
use Modules\Enrollment\Contracts\DTOs\NewEnrollment;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Actions\Plans\SavePlan;
use Modules\Platform\Actions\SaveBranding;
use Modules\Platform\Actions\SaveBrandTemplate;
use Modules\Platform\Branding\BrandTokens;
use Modules\Platform\Branding\BuiltInTemplates;
use Modules\Platform\Contracts\DTOs\NewAcademy;
use Modules\Platform\Contracts\PlatformApi;
use Modules\Platform\Filament\Console\Pages\Academies;
use Modules\Platform\Filament\Console\Pages\AcademyOverview;
use Modules\Platform\Filament\Console\Pages\Plans;
use Modules\Platform\Filament\Pages\AcademyPlanPage;
use Modules\Platform\Filament\Pages\BrandingSettings;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Models\SaasSubscription;
use Modules\Platform\Plans\PlanFormat;
use Modules\Platform\Plans\PlanUsage;
use Modules\Platform\Plans\PlanWarning;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\FeatureNotInPlan;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\PlanLimitReached;
use Tests\Support\EnrolledStudent;

uses(EnrolledStudent::class);

/*
 * The academies' SaaS plans (docs/architecture/01, section 1: "باقات SaaS
 * للأكاديميات، بحدود ... وميزات بتتفتح وتتقفل"): teachers and active students
 * held at the door, video minutes and storage watched, areas that open and
 * close with the plan, the console's plans and an academy's own terms, and
 * the staff told before they reach a limit.
 *
 * The fixture (EnrolledStudent): academy "noor" with an admin, two teachers
 * and a student enrolled (an unpaid invoice), Monday 5 October 2026.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->owner = User::factory()->withRole(Role::Owner, Role::Admin)->create();
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    AuditActor::forget();
});

/** A plan of the platform's (no limit and no area unless given). */
function saasPlan(array $attributes = []): SaasPlan
{
    return SaasPlan::query()->create($attributes + ['code' => 'p-'.Str::lower(Str::random(6)), 'name_ar' => 'باقة', 'name_en' => 'Plan', 'features' => []]);
}

/** Puts noor on a plan, as the platform team would. */
function onPlan(SaasPlan $plan, array $limits = [], array $on = [], array $off = []): SaasSubscription
{
    $subscription = app(ChangeAcademyPlan::class)->handle(test()->academy->id, $plan->id, 'For this test', $limits, $on, $off);
    app(PlanWarning::class)->forget(test()->academy->id);

    return $subscription;
}

function newTeacher(string $name): string
{
    return app(IdentityApi::class)->createUser(new NewUser(name: $name, roles: [Role::Teacher], email: Str::slug($name).'@noor.test'));
}

it('starts a new academy on the plan new academies start on, and keeps every plan it was on', function () {
    $this->clearTenant();
    $starter = saasPlan(['code' => 'starter', 'is_default' => true]);
    $growth = saasPlan(['code' => 'growth']);
    $rawda = app(PlatformApi::class)->createAcademy(new NewAcademy(slug: 'rawda', nameAr: 'الروضة', nameEn: 'Rawda', baseCurrency: 'EGP'));

    $first = SaasSubscription::currentFor($rawda->id);
    expect($first->plan->code)->toBe('starter');

    expect(fn () => app(ChangeAcademyPlan::class)->handle($rawda->id, $growth->id, 'up'))->toThrow(ValidationException::class);
    $second = app(ChangeAcademyPlan::class)->handle($rawda->id, $growth->id, 'Agreed upgrade', ['teachers' => 30], ['payroll']);

    expect(SaasSubscription::currentFor($rawda->id)->id)->toBe($second->id)
        ->and($first->fresh()->ended_at)->not->toBeNull()
        ->and($second->limits)->toBe(['teachers' => 30])
        ->and($second->features_on)->toBe(['payroll']);
    $log = DB::connection('pgsql_platform')->table('audit_logs')->where('action', 'academy.plan_changed')->sole();
    expect($log->academy_id)->toBe($rawda->id)->and(json_decode($log->data, true))->toMatchArray(['code' => 'growth', 'from' => 'starter', 'reason' => 'Agreed upgrade']);

    // A plan an academy was on stays as it was.
    expect(fn () => DB::connection('pgsql_platform')->table('saas_subscriptions')->where('id', $first->id)->update(['reason' => 'rewritten']))->toThrow(QueryException::class)
        ->and(fn () => DB::connection('pgsql_platform')->table('saas_subscriptions')->where('id', $first->id)->delete())->toThrow(QueryException::class);
    // Only the platform writes it; the academy reads its own.
    $this->actAsTenant($rawda);
    expect(DB::table('saas_subscriptions')->count())->toBe(2)
        ->and(fn () => DB::table('saas_subscriptions')->where('id', $second->id)->update(['ended_at' => now()]))->toThrow(QueryException::class)
        ->and(DB::table('saas_plans')->count())->toBe(0);
});

it('keeps one plan for new academies, and leaves an archived plan to those already on it', function () {
    $a = app(SavePlan::class)->handle(null, ['code' => 'aa', 'name_ar' => 'أ', 'name_en' => 'A', 'max_teachers' => 3, 'features' => ['crm'], 'is_default' => true]);
    $b = app(SavePlan::class)->handle(null, ['code' => 'bb', 'name_ar' => 'ب', 'name_en' => 'B', 'price_minor' => 19900, 'currency' => 'SAR', 'is_default' => true]);

    expect($a->fresh()->is_default)->toBeFalse()->and($b->fresh()->is_default)->toBeTrue()
        ->and(fn () => app(SavePlan::class)->handle(null, ['code' => 'bb', 'name_ar' => 'ب', 'name_en' => 'B']))->toThrow(ValidationException::class);

    onPlan($a);
    app(SavePlan::class)->handle($a->id, ['code' => 'aa', 'name_ar' => 'أ', 'name_en' => 'A', 'max_teachers' => 5, 'archived' => true]);
    expect(app(AcademyPlan::class)->limit(Limit::Teachers))->toBe(5)
        ->and(fn () => app(ChangeAcademyPlan::class)->handle($this->academy->id, $a->id, 'Same plan, new terms', ['teachers' => 9]))->not->toThrow(ValidationException::class);
});

it('holds the teacher limit for every new teacher, counting only those who can work', function () {
    onPlan(saasPlan(['max_teachers' => 3]));

    $third = newTeacher('Third Teacher');
    try {
        newTeacher('Fourth Teacher');
        $this->fail('The fourth teacher should not fit.');
    } catch (PlanLimitReached $e) {
        expect($e->limit)->toBe(Limit::Teachers)->and($e->max)->toBe(3)
            ->and($e->errors()['plan'][0])->toBe(__('shared::plans.reached.teachers', ['max' => 3]));
    }
    expect(User::query()->where('email', 'fourth-teacher@noor.test')->exists())->toBeFalse();

    // Anyone else still comes in; a disabled teacher frees a place.
    app(IdentityApi::class)->createUser(new NewUser(name: 'A guardian', roles: [Role::Guardian], email: 'g@noor.test'));
    User::query()->whereKey($third)->update(['status' => 'disabled']);
    newTeacher('Fourth Teacher');

    // The academy's own limit over the plan's; null is none.
    onPlan(saasPlan(['max_teachers' => 1]), ['teachers' => null]);
    newTeacher('Fifth Teacher');
    expect(app(AcademyPlan::class)->limit(Limit::Teachers))->toBeNull();
});

it('holds the active students limit at a new enrollment, never for a student already active', function () {
    onPlan(saasPlan(['max_active_students' => 1]));
    $arabic = app(CatalogApi::class)->createSubject('عربي', 'Arabic');
    $enroll = fn (string $student, string $subject) => app(EnrollmentApi::class)->create(new NewEnrollment($student, $subject, $this->teacher->id, CarbonImmutable::now()));

    // The student enrolled already takes the one place, and a second subject is still the same student.
    $enroll($this->student->id, $arabic);
    $newcomer = User::factory()->withRole(Role::Student)->create();
    expect(fn () => $enroll($newcomer->id, $arabic))->toThrow(PlanLimitReached::class);

    onPlan(saasPlan(['max_active_students' => 2]));
    $enroll($newcomer->id, $arabic);
    expect(app(PlanUsage::class)->lines()[1]->used)->toBe(2);
});

it('closes the CRM, payroll and import, screens and routes, when the plan leaves them out', function () {
    Filament::setCurrentPanel('academy');
    onPlan(saasPlan(['features' => []]));
    $this->actingAs($this->owner);

    expect(Gate::allows(Ability::ManageLeads->value))->toBeFalse()
        ->and(Gate::allows(Ability::ManagePayroll->value))->toBeFalse()
        ->and(Gate::allows(Ability::ImportData->value))->toBeFalse()
        ->and(Gate::allows(Ability::ViewBilling->value))->toBeTrue()
        ->and(Leads::canAccess())->toBeFalse();

    onPlan(saasPlan(['features' => ['crm']]));
    expect(Gate::allows(Ability::ManageLeads->value))->toBeTrue()->and(Leads::canAccess())->toBeTrue();

    // The teacher app hears why, and its "me" says which areas there are.
    onPlan(saasPlan(['features' => ['crm']]), [], [], ['crm']);
    $token = $this->teacher->createToken('phone')->plainTextToken;
    $this->clearTenant();
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/teacher/earnings')->assertForbidden()->assertJsonPath('type', 'feature-not-in-plan')->assertJsonPath('feature', 'payroll');
    $this->withToken($token)->getJson('/api/v1/teacher/trials')->assertForbidden()->assertJsonPath('type', 'feature-not-in-plan');
    $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.plan_features', []);

    $this->actAsTenant($this->academy);
    onPlan(saasPlan(['features' => ['payroll', 'crm']]));
    $this->clearTenant();
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/teacher/earnings')->assertOk();
    $this->withToken($token)->getJson('/api/v1/me')->assertJsonPath('data.plan_features', ['crm', 'payroll']);
});

it('offers no online payment outside the plan, and paying by hand stays', function () {
    $invoice = Invoice::query()->findOrFail($this->booked->invoice->id);
    $account = app(SaveGatewayAccount::class)->handle(null, ['gateway' => 'demo', 'label' => 'Demo', 'mode' => 'test', 'secret_key' => 'sk_test_demo', 'webhook_secret' => 'whsec_demo'], $this->admin->id);
    expect(app(OnlinePayments::class)->offers('SAR'))->toBeTrue();

    onPlan(saasPlan(['features' => []]));
    $online = app(OnlinePayments::class);
    expect($online->offers('SAR'))->toBeFalse()
        ->and($online->accountsFor($invoice))->toBe([])
        ->and(fn () => app(StartCheckout::class)->handle($invoice, $account, null, 'link'))->toThrow(FeatureNotInPlan::class)
        ->and(fn () => app(SaveGatewayAccount::class)->handle(null, ['gateway' => 'demo', 'label' => 'Again', 'mode' => 'test'], $this->admin->id))->toThrow(FeatureNotInPlan::class)
        ->and(GatewayAccount::query()->count())->toBe(1);
});

it('lets an academy pick a ready template or keep its look outside the plan, not make its own', function () {
    $builtIn = BuiltInTemplates::all()['noor']['tokens'];
    $mine = BrandTokens::fromArray(['primary' => '#7A1F5C'] + $builtIn->toArray());
    app(SaveBranding::class)->handle($mine, null);

    onPlan(saasPlan(['features' => []]));
    app(SaveBranding::class)->handle($mine, null);                       // the look it already has
    app(SaveBranding::class)->handle($builtIn, 'builtin:noor');           // a ready template
    expect(fn () => app(SaveBranding::class)->handle($mine, null))->toThrow(FeatureNotInPlan::class)
        ->and(fn () => app(SaveBrandTemplate::class)->handle('Ours', $mine))->toThrow(FeatureNotInPlan::class);

    // The screen says so instead of failing.
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->owner);
    Livewire::test(BrandingSettings::class)
        ->assertSee(__('platform::branding.custom_not_in_plan'))
        ->set('data.primary', '#123456')
        ->call('save')
        ->assertNotified(__('platform::plans.stopped'));
});

it('counts each limit with the module that owns it, keeps the day\'s count, and tells the staff', function () {
    // A lesson held on our own video: its room opened.
    $lesson = DB::table('sessions')->where('enrollment_id', $this->booked->enrollmentId)->orderBy('starts_at')->first();
    DB::table('rooms')->insert(['id' => (string) Str::uuid7(), 'academy_id' => $this->academy->id, 'session_id' => $lesson->id, 'provider' => 'livekit', 'name' => 'r', 'started_at' => $lesson->starts_at, 'created_at' => now(), 'updated_at' => now()]);
    onPlan(saasPlan(['max_teachers' => 2, 'max_active_students' => 10, 'video_minutes' => 30, 'storage_mb' => 100]));

    $lines = collect(app(PlanUsage::class)->lines())->keyBy(fn ($l) => $l->limit->value);
    expect($lines['teachers']->used)->toBe(2)->and($lines['teachers']->state())->toBe('full')
        ->and($lines['active_students']->used)->toBe(1)->and($lines['active_students']->state())->toBe('ok')
        ->and($lines['video_minutes']->used)->toBe(30)
        ->and($lines['storage_mb']->used)->toBe(0);

    // The owner hears it on every page; a teacher does not.
    $this->actingAs($this->owner);
    $warning = app(PlanWarning::class)->forViewer();
    expect($warning['state'])->toBe('full')->and($warning['text'])->toContain(__('shared::plans.limits.teachers'));
    $this->actingAs($this->teacher);
    expect(app(PlanWarning::class)->forViewer())->toBeNull();

    // The night's count, for the console.
    $this->clearTenant();
    $this->artisan('platform:usage')->assertSuccessful();
    $counted = DB::connection('pgsql_platform')->table('usage_counters')->where('academy_id', $this->academy->id)->pluck('value', 'metric');
    expect($counted->all())->toMatchArray(['teachers' => 2, 'active_students' => 1, 'video_minutes' => 30, 'storage_mb' => 0])
        ->and(DB::connection('pgsql_platform')->table('usage_counters')->value('period'))->toBe('2026-10-01');

    // The academy's own page, for its owner and admins only.
    $this->actAsTenant($this->academy);
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->owner);
    Livewire::test(AcademyPlanPage::class)->assertOk()->assertSee(__('platform::plans.summary.state.full'))->assertSee(__('platform::plans.of', ['used' => 2, 'max' => 2]));
    $this->actingAs($this->teacher);
    expect(AcademyPlanPage::canAccess())->toBeFalse();
});

it('shows the console the plans, each academy\'s plan and use, and changes it with a reason', function () {
    $this->clearTenant();
    $admin = PlatformAdmin::query()->create(['name' => 'سلمى', 'email' => 'salma@platform.test', 'password' => 'a-long-password', 'timezone' => 'Africa/Cairo']);
    Filament::setCurrentPanel('console');
    $this->actingAs($admin, 'platform');
    AuditActor::use(new AuditActor('platform', $admin->id, $admin->name));
    app()->setLocale('ar');

    // The plans with how many academies are on each (noor on one already).
    onPlan(saasPlan(['code' => 'starter', 'name_ar' => 'البداية']));
    Livewire::test(Plans::class)->assertSee('البداية')->assertSee(trans_choice('platform::plans.catalog.academies', 1, ['count' => 1]));

    Livewire::test(Plans::class)
        ->callAction('new', ['code' => 'growth', 'name_ar' => 'النمو', 'name_en' => 'Growth', 'max_teachers' => '20', 'features' => ['crm'], 'price' => '199', 'currency' => 'SAR', 'is_default' => true])
        ->assertHasNoActionErrors()
        ->assertSee('النمو');
    $growth = SaasPlan::query()->where('code', 'growth')->sole();
    expect($growth->max_teachers)->toBe(20)->and($growth->max_active_students)->toBeNull()->and($growth->price_minor)->toBe(19900)->and($growth->is_default)->toBeTrue();

    Livewire::test(AcademyOverview::class, ['academy' => $this->academy->id])
        ->callAction('plan', ['plan' => $growth->id, 'limits' => ['teachers' => '25', 'active_students' => ''], 'features_on' => ['payroll'], 'features_off' => [], 'reason' => 'Agreed in the call'])
        ->assertHasNoActionErrors()
        ->call('countNow')
        ->assertSee('النمو')
        ->assertSee(__('platform::plans.of', ['used' => 2, 'max' => 25]));

    // The academies list says each one's plan, and when a limit is close or reached.
    Livewire::test(Academies::class)->assertSee('النمو')->assertDontSee(__('platform::plans.summary.state.full'));
    DB::connection('pgsql_platform')->table('usage_counters')->where('academy_id', $this->academy->id)->where('metric', 'teachers')->update(['max' => 2]);
    Livewire::test(Academies::class)->assertSee(__('platform::plans.summary.state.full'));

    $current = SaasSubscription::currentFor($this->academy->id);
    expect($current->plan_id)->toBe($growth->id)->and($current->limits)->toBe(['teachers' => 25])->and($current->features_on)->toBe(['payroll'])
        ->and($current->changed_by)->toBe($admin->id);
});

it('writes limits and use as people read them', function () {
    app()->setLocale('en');

    expect(PlanFormat::usage(Limit::Teachers, 9, 10))->toBe('9 of 10')
        ->and(PlanFormat::amount(Limit::VideoMinutes, 15000))->toBe('15,000 minutes')
        ->and(PlanFormat::amount(Limit::Teachers, 1))->toBe('1 teacher')
        ->and(PlanFormat::amount(Limit::StorageMb, 25 * 1024))->toBe('25 GB')
        ->and(PlanFormat::amount(Limit::StorageMb, 1536))->toBe('1.5 GB')
        ->and(PlanFormat::usage(Limit::StorageMb, 300, 5120))->toBe('300 MB of 5 GB')
        ->and(PlanFormat::amount(Limit::ActiveStudents, null))->toBe(__('platform::plans.unlimited'));
});
