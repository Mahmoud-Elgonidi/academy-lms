<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Billing\Actions\SavePlan;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\NewSubscription;
use Modules\Billing\Filament\Resources\Coupons\ManageCoupons;
use Modules\Billing\Filament\Resources\Plans\ManagePlans;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Enrollment\Contracts\DTOs\NewEnrollment;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

/*
 * The back office's price list and coupons (docs/architecture/01, "الباقات
 * والأسعار"; 02, section 3, "الخصومات"), as the accountant uses them: each
 * screen opens and saves through its Action, and what is fixed once a
 * family relies on it (a plan's kind, a used coupon's terms) stays fixed.
 */

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->quran = app(CatalogApi::class)->createSubject('قرآن', 'Quran');
    $this->teacher = User::factory()->withRole(Role::Teacher)->create();
    $this->gulf = PriceRegion::query()->create(['name_ar' => 'الخليج', 'name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => true, 'sort' => 0]);
    $this->kuwait = PriceRegion::query()->create(['name_ar' => 'الكويت', 'name_en' => 'Kuwait', 'currency' => 'KWD', 'sort' => 1]);
    $this->actingAs($this->accountant);
    Filament::setCurrentPanel('academy');
    Repeater::fake();
});

/** A student enrolled on the plan, paid by themselves, with this coupon if given. */
function screensSubscribe(Plan $plan, ?string $coupon = null): void
{
    $t = test();
    $student = User::factory()->withRole(Role::Student)->create();
    $enrollment = app(EnrollmentApi::class)->create(new NewEnrollment($student->id, $t->quran, $t->teacher->id, CarbonImmutable::now()));
    $account = app(CreditsApi::class)->openAccount($student->id, 'enrollment', $enrollment);
    app(EnrollmentApi::class)->attachCreditAccount($enrollment, $account);
    app(BillingApi::class)->subscribe(new NewSubscription($enrollment, $student->id, $account, $plan->id, $student->id, $t->gulf->id, 'قرآن', 'Quran', couponCode: $coupon));
}

function screensPlanForm(array $changes = []): array
{
    return $changes + [
        'name_ar' => '8 حصص', 'name_en' => '8 lessons', 'credit_scope' => 'enrollment', 'session_minutes' => 45,
        'sessions_per_week' => 2, 'sessions_per_block' => 8, 'validity_days' => 42, 'renew_at_sessions' => 2, 'is_active' => true,
        'prices' => [['price_region_id' => test()->gulf->id, 'amount' => '350'], ['price_region_id' => test()->kuwait->id, 'amount' => '12.345']],
    ];
}

it('makes and changes plans from their screen, in each region\'s currency, and keeps a plan\'s kind once someone is on it', function () {
    $page = Livewire::test(ManagePlans::class)->assertOk()
        ->callAction('create', data: screensPlanForm())->assertHasNoActionErrors();

    $plan = Plan::query()->with('prices')->sole();
    expect([$plan->session_minutes, $plan->sessions_per_block, $plan->credit_scope, $plan->is_active])->toBe([45, 8, 'enrollment', true])
        ->and($plan->prices->mapWithKeys(fn ($p) => [$p->currency => $p->amount_minor])->sort()->all())->toBe(['KWD' => 12345, 'SAR' => 35000]);
    $page->assertCanSeeTableRecords([$plan]);
    // A price is money: who set it is in the activity log.
    $logged = DB::table('audit_logs')->where('action', 'billing.plan_saved')->where('subject_id', $plan->id)->sole();
    expect($logged->actor_id)->toBe($this->accountant->id)
        ->and(json_decode($logged->data, true)['prices'])->toContain('350')->toContain('12.345');

    // Nobody is on it yet: it may still become a shared package, and back.
    $page->callTableAction('edit', $plan, screensPlanForm(['credit_scope' => Plan::SHARED]))->assertHasNoTableActionErrors();
    expect($plan->refresh()->credit_scope)->toBe(Plan::SHARED);
    $page->callTableAction('edit', $plan, screensPlanForm())->assertHasNoTableActionErrors();

    // Once a student is on it, the kind and the shape stay: the fields are locked, and changed values are not taken.
    screensSubscribe($plan->refresh());
    Livewire::test(ManagePlans::class)
        ->callTableAction('edit', $plan, screensPlanForm(['name_en' => '8 lessons a month', 'credit_scope' => Plan::SHARED,
            'session_minutes' => 60, 'sessions_per_week' => 3, 'sessions_per_block' => 12, 'validity_days' => 50]))->assertHasNoTableActionErrors();
    expect($plan->refresh()->only(['name_en', 'credit_scope', 'session_minutes', 'sessions_per_week', 'sessions_per_block', 'validity_days']))
        ->toBe(['name_en' => '8 lessons a month', 'credit_scope' => 'enrollment', 'session_minutes' => 45, 'sessions_per_week' => 2, 'sessions_per_block' => 8, 'validity_days' => 50]);

    // Switched off, not deleted: its prices stay.
    Livewire::test(ManagePlans::class)->callTableAction('toggle', $plan);
    expect($plan->refresh()->is_active)->toBeFalse()->and($plan->prices()->count())->toBe(2);
});

it('keeps a plan\'s shape once someone is on it: another shape is another plan', function () {
    $terms = ['name_ar' => '8 حصص', 'name_en' => '8 lessons', 'session_minutes' => 45, 'sessions_per_week' => 2, 'sessions_per_block' => 8, 'validity_days' => 42,
        'prices' => [['price_region_id' => $this->gulf->id, 'amount' => '350']]];
    $plan = app(SavePlan::class)->handle(null, $terms);

    // Nobody on it yet: its shape still changes.
    app(SavePlan::class)->handle($plan, ['session_minutes' => 60] + $terms);
    expect($plan->refresh()->session_minutes)->toBe(60);
    app(SavePlan::class)->handle($plan, $terms);

    screensSubscribe($plan->refresh());
    foreach (['session_minutes' => 60, 'sessions_per_week' => 3, 'sessions_per_block' => 12] as $field => $value) {
        expect(fn () => app(SavePlan::class)->handle($plan->refresh(), [$field => $value] + $terms))
            ->toThrow(ValidationException::class, __('billing::plans.errors.shape_locked'));
    }

    // Its name, prices, validity and renewal point still change, and the shape stays.
    app(SavePlan::class)->handle($plan->refresh(), ['name_en' => 'Eight', 'validity_days' => 49, 'renew_at_sessions' => 3,
        'prices' => [['price_region_id' => $this->gulf->id, 'amount' => '380']]] + $terms);
    expect($plan->refresh()->only(['name_en', 'session_minutes', 'sessions_per_week', 'sessions_per_block', 'validity_days', 'renew_at_sessions']))
        ->toBe(['name_en' => 'Eight', 'session_minutes' => 45, 'sessions_per_week' => 2, 'sessions_per_block' => 8, 'validity_days' => 49, 'renew_at_sessions' => 3])
        ->and($plan->prices()->value('amount_minor'))->toBe(38000);
});

it('saves the regions dialog as loaded without losing a region or a price, adds one once payers are billed, and sets the tax', function () {
    $plan = Plan::query()->create(['name_ar' => '4 حصص', 'name_en' => '4 lessons', 'session_minutes' => 30, 'sessions_per_block' => 4, 'sessions_per_week' => 1, 'validity_days' => 42]);
    $plan->prices()->create(['price_region_id' => $this->gulf->id, 'amount_minor' => 20000, 'currency' => 'SAR']);
    $plan->prices()->create(['price_region_id' => $this->kuwait->id, 'amount_minor' => 12345, 'currency' => 'KWD']);
    $ids = fn () => PriceRegion::query()->orderBy('sort')->pluck('id')->all();
    $before = $ids();

    // Saved as it opens: the same regions, the plan's prices untouched.
    Livewire::test(ManagePlans::class)->callAction('regions')->assertHasNoActionErrors()->assertNotified(__('billing::plans.saved'));
    expect($ids())->toBe($before)
        ->and($plan->prices()->count())->toBe(2);

    // A family pays in the Gulf; a region can still be added, the others kept as they are.
    screensSubscribe($plan->refresh());
    Livewire::test(ManagePlans::class)->mountAction('regions')
        ->set('mountedActions.0.data.regions.new', ['id' => null, 'name_ar' => 'مصر', 'name_en' => 'Egypt', 'currency' => 'EGP', 'is_default' => false])
        ->callMountedAction()->assertHasNoActionErrors();
    expect(PriceRegion::query()->orderBy('sort')->pluck('currency')->all())->toBe(['SAR', 'KWD', 'EGP'])
        ->and(array_slice($ids(), 0, 2))->toBe($before)
        ->and($plan->prices()->count())->toBe(2);

    // Kuwait's plan is priced in dinars: it does not move to another currency.
    Livewire::test(ManagePlans::class)->mountAction('regions')
        ->set('mountedActions.0.data.regions.1.currency', 'BHD')
        ->callMountedAction()
        ->assertNotified(__('billing::plans.errors.region_priced', ['currency' => 'KWD']));
    expect($this->kuwait->refresh()->currency)->toBe('KWD');

    Livewire::test(ManagePlans::class)
        ->callAction('tax', data: ['tax_percent' => '15', 'prices_include_tax' => true, 'tax_number' => null, 'invoice_prefix' => 'NR-', 'due_days' => 5, 'seller_name' => 'Noor', 'zatca' => false])
        ->assertHasNoActionErrors();
    expect(BillingSettings::current()->only(['tax_rate_bp', 'invoice_prefix', 'due_days']))->toBe(['tax_rate_bp' => 1500, 'invoice_prefix' => 'NR-', 'due_days' => 5]);
});

it('makes coupons from their screen, and keeps what a used coupon takes off', function () {
    $plan = Plan::query()->create(['name_ar' => '8 حصص', 'name_en' => '8 lessons', 'session_minutes' => 30, 'sessions_per_block' => 8, 'sessions_per_week' => 2, 'validity_days' => 42]);
    $plan->prices()->create(['price_region_id' => $this->gulf->id, 'amount_minor' => 40000, 'currency' => 'SAR']);
    $page = Livewire::test(ManageCoupons::class)->assertOk();

    $page->callAction('create', data: ['code' => 'eid-10', 'name' => 'Eid', 'kind' => 'percent', 'percent' => '10', 'blocks' => 1, 'max_uses' => 50, 'plan_ids' => [$plan->id], 'is_active' => true])
        ->assertHasNoActionErrors();
    $page->callAction('create', data: ['code' => 'KW5', 'kind' => 'amount', 'amount' => '5.5', 'currency' => 'KWD', 'is_active' => true])->assertHasNoActionErrors();
    $eid = Coupon::query()->where('code', 'EID-10')->sole();
    expect([$eid->percent_bp, $eid->blocks, $eid->max_uses, $eid->plan_ids])->toBe([1000, 1, 50, [$plan->id]])
        ->and(Coupon::query()->where('code', 'KW5')->sole()->only(['amount_minor', 'currency']))->toBe(['amount_minor' => 5500, 'currency' => 'KWD']);

    // A code already in use is refused, and said so.
    Livewire::test(ManageCoupons::class)->callAction('create', data: ['code' => 'Eid-10', 'kind' => 'percent', 'percent' => '20', 'is_active' => true])
        ->assertNotified(__('billing::discounts.errors.code_taken'));
    expect(Coupon::query()->count())->toBe(2);

    // Once a family has it, what it takes off stays; its name and uses can still change.
    screensSubscribe($plan, 'EID-10');
    $edit = fn (array $changes) => ['code' => 'EID-10', 'name' => 'Eid', 'kind' => 'percent', 'percent' => '10', 'blocks' => 1, 'max_uses' => 50, 'plan_ids' => [$plan->id], 'with_sibling' => false, 'is_active' => true, ...$changes];
    Livewire::test(ManageCoupons::class)->callTableAction('edit', $eid, $edit(['percent' => '25']))->assertNotified(__('billing::discounts.errors.used_terms'));
    expect($eid->refresh()->percent_bp)->toBe(1000);
    Livewire::test(ManageCoupons::class)->callTableAction('edit', $eid, $edit(['name' => 'Eid al-Adha', 'max_uses' => 80]))->assertHasNoTableActionErrors();
    expect($eid->refresh()->only(['name', 'max_uses', 'percent_bp']))->toBe(['name' => 'Eid al-Adha', 'max_uses' => 80, 'percent_bp' => 1000])
        ->and($eid->uses())->toBe(1);
});
