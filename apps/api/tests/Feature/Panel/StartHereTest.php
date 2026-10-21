<?php

use App\Filament\Academy\StartHere;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Modules\Billing\Actions\Payments\SaveGatewayAccount;
use Modules\Billing\Actions\SavePlan;
use Modules\Billing\Actions\SavePriceRegions;
use Modules\Billing\Actions\SaveTransferDetails;
use Modules\Billing\Models\PriceRegion;
use Modules\Catalog\Actions\SaveSubject;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Actions\Directory\AddTeacher;
use Modules\People\Actions\Directory\SetPersonActive;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\Platform\Actions\UpdateAcademyIdentity;
use Modules\Privacy\Actions\SavePrivacySettings;
use Modules\Scheduling\Actions\Policy\SavePolicySettings;
use Modules\Scheduling\Actions\Shifts\ApproveShiftProposal;
use Modules\Scheduling\Actions\Shifts\CounterShiftProposal;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Domain\Policy\PolicySettings;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\SharedKernel\Setup\SetupChecklist;
use Modules\SharedKernel\Setup\SetupStep;

/*
 * "Start here" (docs/architecture/02, "قايمة تجهيز الأكاديمية"): a new
 * academy's essentials, each read from its own module's data; the owner and
 * admins land on it after signing in until they are done, nobody else ever
 * does, and its reading stays a query per step.
 */

const SETUP_REQUIRED = ['platform.branding', 'scheduling.policy', 'privacy.details', 'catalog.subjects', 'people.teacher', 'scheduling.teacher_hours', 'billing.prices', 'billing.payment'];
const SETUP_OPTIONAL = ['identity.team', 'site.page', 'curriculum.program', 'import.people'];

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->owner = User::factory()->withRole(Role::Owner)->create(['locale' => 'en']);
    $this->admin = User::factory()->withRole(Role::Admin)->create(['locale' => 'en']);
    $this->scheduler = User::factory()->withRole(Role::Scheduler)->create();
    $this->accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->clearTenant();
});

/** Signs in on the panels as a sign-in page does (Laravel's Login event), so the next visit to the home is the landing. */
function setupSignIn(User $user): void
{
    test()->actAsTenant(test()->academy);
    app('auth')->forgetGuards();
    Auth::guard('web')->login($user);
    test()->clearTenant();
}

/** @return array<string, bool> each step's key => done, read now */
function setupState(): array
{
    test()->actAsTenant(test()->academy);
    $progress = app(SetupChecklist::class)->progress();

    return collect($progress->steps())->mapWithKeys(fn (SetupStep $step) => [$step->key() => $progress->isDone($step)])->all();
}

/** The step's row on the page: whether it shows as done, greyed, and with a way to its screen. */
function setupRow(TestResponse $page, string $key): string
{
    preg_match('/<li class="sh-step" data-step="'.preg_quote($key, '/').'".*?<\/li>/s', (string) $page->getContent(), $m);

    return $m[0] ?? '';
}

it('lists every module\'s step in its part, the essentials apart from the optional ones', function () {
    $this->actAsTenant($this->academy);
    $progress = app(SetupChecklist::class)->progress();

    expect(array_map(fn (SetupStep $s) => $s->key(), $progress->required()))->toBe(SETUP_REQUIRED)
        ->and(array_map(fn (SetupStep $s) => $s->key(), $progress->optional()))->toBe(SETUP_OPTIONAL);
});

it('shows a new academy every essential undone, and lands its owner and admins on "Start here" after signing in', function () {
    expect(array_intersect_key(setupState(), array_flip(SETUP_REQUIRED)))->each->toBeFalse();

    setupSignIn($this->owner);
    $this->get('http://noor.lms.test/admin')->assertRedirect('http://noor.lms.test/admin/start-here');

    $page = $this->get('http://noor.lms.test/admin/start-here')->assertOk()
        ->assertSee('Start here')
        ->assertSee('8 steps left and your academy is ready.')
        ->assertSee('aria-valuenow="0"', false)
        ->assertSee('lms-bar-fill', false);
    foreach (SETUP_REQUIRED as $key) {
        expect(setupRow($page, $key))->not->toBe('')->not->toContain('data-done');
    }
    // The owner may do every step here: each has its way to its screen.
    expect(setupRow($page, 'billing.prices'))->toContain('href="http://noor.lms.test/admin/settings/plans"')
        ->and(setupRow($page, 'scheduling.policy'))->toContain('href="http://noor.lms.test/admin/settings/policy"')
        ->and(setupRow($page, 'platform.branding'))->toContain('href="http://noor.lms.test/admin/settings/branding"');

    // The landing is once a sign-in: the live board stays a click away.
    $this->get('http://noor.lms.test/admin')->assertOk();

    setupSignIn($this->admin);
    $this->get('http://noor.lms.test/admin')->assertRedirect('http://noor.lms.test/admin/start-here');

    // Two-step sign-in is set up first; the landing waits for it.
    $this->actAsTenant($this->academy);
    $newAdmin = User::factory()->withRole(Role::Admin)->withoutTwoFactor()->create();
    setupSignIn($newAdmin);
    $this->get('http://noor.lms.test/admin')->assertRedirect('http://noor.lms.test/admin/multi-factor-authentication/set-up');
    $this->actAsTenant($this->academy);
    $newAdmin->forceFill(['app_authentication_secret' => UserFactory::TWO_FACTOR_SECRET])->saveQuietly();
    $this->clearTenant();
    $this->get('http://noor.lms.test/admin')->assertRedirect('http://noor.lms.test/admin/start-here');
});

it('ticks each essential off as its own screen\'s action does it, and opens the live board once they are all done', function () {
    $this->actAsTenant($this->academy);
    $this->actingAs($this->owner);
    $done = fn (string $key) => setupState()[$key];

    app(UpdateAcademyIdentity::class)->handle('أكاديمية نور', 'Noor Academy', null, null);
    expect($done('platform.branding'))->toBeFalse();
    app(UpdateAcademyIdentity::class)->handle('أكاديمية نور', 'Noor Academy', UpdateAcademyIdentity::directory($this->academy->id).'/logo.png', null);
    expect($done('platform.branding'))->toBeTrue();

    // The defaults the first lesson needed are nobody's review; keeping them on the policy screen is.
    PolicyVersion::current();
    expect($done('scheduling.policy'))->toBeFalse();
    app(SavePolicySettings::class)->handle(PolicySettings::defaults()->toArray(), $this->owner->id);
    expect($done('scheduling.policy'))->toBeTrue()
        ->and(PolicyVersion::query()->count())->toBe(2);

    app(SavePrivacySettings::class)->handle(['legal_name' => 'مؤسسة نور للتعليم'], $this->owner->id);
    expect($done('privacy.details'))->toBeFalse();
    app(SavePrivacySettings::class)->handle(['legal_name' => 'مؤسسة نور للتعليم', 'contact_email' => 'privacy@noor.test'], $this->owner->id);
    expect($done('privacy.details'))->toBeTrue();

    $subject = app(SaveSubject::class)->handle(null, 'قرآن', 'Quran');
    expect($done('catalog.subjects'))->toBeTrue();

    $teacher = app(AddTeacher::class)->handle(new UserDetails('Sara', 'sara@noor.test'), new TeacherProfileData('', 'female', ['ar'], [$subject->id]), false);
    expect($done('people.teacher'))->toBeTrue()
        ->and($done('scheduling.teacher_hours'))->toBeFalse();

    $proposal = app(CounterShiftProposal::class)->handle($teacher, WeeklyShifts::fromArray([['weekday' => 6, 'start' => '17:00', 'end' => '21:00']], 'Africa/Cairo'),
        CarbonImmutable::now()->addDay(), null, $this->owner->id);
    expect($done('scheduling.teacher_hours'))->toBeFalse();
    app(ApproveShiftProposal::class)->handle($proposal->refresh(), $teacher, 'teacher');
    expect($done('scheduling.teacher_hours'))->toBeTrue();

    // A teacher turned off is no teacher to book.
    app(SetPersonActive::class)->handle($teacher, false);
    expect($done('people.teacher'))->toBeFalse()->and($done('scheduling.teacher_hours'))->toBeFalse();
    app(SetPersonActive::class)->handle($teacher, true);

    app(SavePriceRegions::class)->handle([['name_ar' => 'الخليج', 'name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => true]]);
    $plan = ['name_ar' => '8 حصص', 'name_en' => '8 lessons', 'session_minutes' => 30, 'sessions_per_block' => 8, 'sessions_per_week' => 2];
    app(SavePlan::class)->handle(null, $plan);
    expect($done('billing.prices'))->toBeFalse();
    app(SavePlan::class)->handle(null, $plan + ['prices' => [['price_region_id' => PriceRegion::query()->value('id'), 'amount' => '400']]]);
    expect($done('billing.prices'))->toBeTrue()
        ->and(app(SetupChecklist::class)->progress()->requiredLeft())->toBe(1);

    // Still one left: signing in lands on "Start here", which says so.
    setupSignIn($this->owner);
    $this->get('http://noor.lms.test/admin')->assertRedirect('http://noor.lms.test/admin/start-here');
    $page = $this->get('http://noor.lms.test/admin/start-here')->assertOk()->assertSee('One step left and your academy is ready.');
    expect(setupRow($page, 'billing.prices'))->toContain('data-done')
        ->and(setupRow($page, 'billing.payment'))->not->toContain('data-done');

    $this->actAsTenant($this->academy);
    app(SaveTransferDetails::class)->handle("Al Rajhi Bank\nIBAN SA00 0000 0000 0000");
    expect($done('billing.payment'))->toBeTrue()
        ->and(app(SetupChecklist::class)->progress()->complete())->toBeTrue();

    setupSignIn($this->owner);
    $this->get('http://noor.lms.test/admin')->assertOk();
    $this->get('http://noor.lms.test/admin/start-here')->assertOk()
        ->assertSee('All the essentials are ready')
        ->assertSee('aria-valuenow="100"', false);
});

it('takes an online gateway that is on, or a price region\'s own transfer details, as a way to pay', function () {
    $this->actAsTenant($this->academy);
    $done = fn () => setupState()['billing.payment'];

    $account = app(SaveGatewayAccount::class)->handle(null, ['gateway' => 'demo', 'label' => 'Card', 'mode' => 'test'], $this->owner->id);
    expect($done())->toBeTrue();
    $account->forceFill(['is_active' => false])->save();
    expect($done())->toBeFalse();

    app(SavePriceRegions::class)->handle([['name_ar' => 'مصر', 'name_en' => 'Egypt', 'currency' => 'EGP', 'is_default' => true]]);
    app(SaveTransferDetails::class)->handle(null, [PriceRegion::query()->value('id') => 'InstaPay: noor@instapay']);
    expect($done())->toBeTrue();
});

it('never sends anyone else to "Start here", and keeps the page from them', function () {
    setupSignIn($this->scheduler);
    $this->get('http://noor.lms.test/admin')->assertOk();
    $this->get('http://noor.lms.test/admin/start-here')->assertForbidden();

    setupSignIn($this->accountant);
    $this->get('http://noor.lms.test/admin')->assertRedirect('http://noor.lms.test/admin/revenue');
    $this->get('http://noor.lms.test/admin/start-here')->assertForbidden();

    $this->actAsTenant($this->academy);
    $teacher = User::factory()->withRole(Role::Teacher)->create();
    setupSignIn($teacher);
    $this->get('http://noor.lms.test/admin')->assertForbidden();
    $this->get('http://noor.lms.test/admin/start-here')->assertForbidden();
});

it('greys the steps a viewer may not do, with no way to their screen, and still counts them', function () {
    $this->actingAs($this->admin);
    $page = $this->get('http://noor.lms.test/admin/start-here')->assertOk()->assertSee('8 steps left');

    // Prices and payment are the owner's and the accountant's (billing.manage).
    foreach (['billing.prices', 'billing.payment'] as $key) {
        expect(setupRow($page, $key))->toContain('data-locked')->not->toContain('href=');
    }
    expect(setupRow($page, 'catalog.subjects'))->not->toContain('data-locked')->toContain('href="http://noor.lms.test/admin/subjects"');
});

it('heads the menu with what is left, and moves down to Settings once the essentials are done', function () {
    $this->actAsTenant($this->academy);
    $this->actingAs($this->owner);
    Filament\Facades\Filament::setCurrentPanel('academy');

    $item = fn () => collect(StartHere::getNavigationItems())->sole();
    expect($item()->getBadge())->toBe('8')->and($item()->getGroup())->toBeNull()->and($item()->getSort())->toBeLessThan(0);

    app()->instance(SetupChecklist::class, new SetupChecklist(app()));   // nothing left to set up
    request()->attributes->remove(StartHere::class);
    expect($item()->getBadge())->toBeNull()->and($item()->getGroup())->toBe(__('platform::branding.nav_group'))->and($item()->getSort())->toBeGreaterThan(0);
});

it('reads each step once for its page, the menu and the landing together', function () {
    setupSignIn($this->owner);
    $this->get('http://noor.lms.test/admin/start-here')->assertOk();   // warms what a request keeps
    $steps = count(app(SetupChecklist::class)->steps());

    $queries = function (string $url, int $status = 200): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertStatus($status);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    // The landing on a new academy: the first essential not done, and nothing more.
    setupSignIn($this->owner);
    $landing = $queries('http://noor.lms.test/admin', 302);
    $withSteps = $queries('http://noor.lms.test/admin/start-here');

    $real = app(SetupChecklist::class);
    app()->instance(SetupChecklist::class, new SetupChecklist(app()));
    setupSignIn($this->owner);
    $landingWithout = $queries('http://noor.lms.test/admin');   // nothing to set up: no redirect, the board renders
    $withoutSteps = $queries('http://noor.lms.test/admin/start-here');
    app()->instance(SetupChecklist::class, $real);

    expect($steps)->toBe(12)
        ->and($withSteps - $withoutSteps)->toBeLessThanOrEqual($steps)   // the page, the menu's place and badge: one reading
        ->and($landing)->toBeLessThanOrEqual(5)                          // a redirect: the account, its roles, the first step not done
        ->and($landingWithout)->toBeGreaterThan($landing);
});
