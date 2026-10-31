<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use Modules\Billing\Actions\SavePlan;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\NewSubscription;
use Modules\Billing\Filament\Resources\Plans\ManagePlans;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Enrollment\Contracts\DTOs\NewEnrollment;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Money\Money;

/*
 * Settings › Plans for group lessons (docs/architecture/02, "الحصص
 * الجماعية"): the format chosen first, as two cards; a group plan is never
 * a shared package; its price is set beside the private plan of the same
 * shape; and the format stays once someone is on the plan.
 */

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->accountant = User::factory()->withRole(Role::Accountant)->create();
    $this->gulf = PriceRegion::query()->create(['name_ar' => 'الخليج', 'name_en' => 'Gulf', 'currency' => 'SAR', 'is_default' => true, 'sort' => 0]);
    $this->private = Plan::query()->create(['name_ar' => '8 حصص', 'name_en' => '8 lessons', 'session_minutes' => 30, 'sessions_per_block' => 8, 'sessions_per_week' => 2, 'validity_days' => 42]);
    $this->private->prices()->create(['price_region_id' => $this->gulf->id, 'amount_minor' => 40000, 'currency' => 'SAR']);
    $this->actingAs($this->accountant);
    Filament::setCurrentPanel('academy');
    Repeater::fake();
    app()->setLocale('ar');
});

function groupPlanForm(array $changes = []): array
{
    return $changes + [
        'lesson_format' => 'group', 'name_ar' => 'مجموعة 8 حصص', 'name_en' => 'Group 8', 'credit_scope' => 'enrollment', 'session_minutes' => 30,
        'sessions_per_week' => 2, 'sessions_per_block' => 8, 'validity_days' => 42, 'renew_at_sessions' => 2, 'is_active' => true,
        'prices' => [['price_region_id' => test()->gulf->id, 'amount' => '260']],
    ];
}

it('chooses the format first as two cards, prices a group plan against the private one, and saves it per course', function () {
    $page = Livewire::test(ManagePlans::class)->mountAction('create')
        ->assertMountedActionModalSee([__('billing::plans.formats.private.title'), __('billing::plans.formats.group.title'), __('billing::plans.formats.group.line', ['max' => 20])])
        ->assertMountedActionModalSeeHtml(['data-value="private"', 'data-value="group"', 'data-tone="group"'])
        // Private by default; the shared package is offered only for private plans.
        ->assertMountedActionModalSee(__('billing::plans.scopes.student_pool'));

    // Group: no shared package, and the lesson's price beside the private one (260/8 = 32.50 against 400/8 = 50: 35% less).
    $page->fillForm(groupPlanForm(['credit_scope' => Plan::SHARED]))
        ->assertMountedActionModalDontSee(__('billing::plans.scopes.student_pool'))
        ->assertMountedActionModalSee(__('billing::plans.compare.less', ['region' => 'الخليج', 'lesson' => Money::of(3250, 'SAR')->format(), 'pct' => 35, 'plan' => '8 حصص', 'private' => Money::of(5000, 'SAR')->format()]));
    $page->callMountedAction()->assertHasNoActionErrors();

    $plan = Plan::query()->where('lesson_format', 'group')->sole();
    expect($plan->credit_scope)->toBe('enrollment')->and($plan->isGroup())->toBeTrue();

    // Priced above the private lesson: said so.
    Livewire::test(ManagePlans::class)->mountAction('create')->fillForm(groupPlanForm(['prices' => [['price_region_id' => $this->gulf->id, 'amount' => '480']]]))
        ->assertMountedActionModalSee(__('billing::plans.compare.more', ['region' => 'الخليج', 'lesson' => Money::of(6000, 'SAR')->format(), 'pct' => 20, 'plan' => '8 حصص', 'private' => Money::of(5000, 'SAR')->format()]));
});

it('shows the format in the table with a group badge and a filter, and keeps a plan\'s format once someone is on it', function () {
    $group = app(SavePlan::class)->handle(null, groupPlanForm());

    Livewire::test(ManagePlans::class)
        ->assertCanSeeTableRecords([$group, $this->private])
        ->assertTableColumnFormattedStateSet('lesson_format', __('billing::plans.formats.group.title'), $group)
        ->assertTableColumnFormattedStateSet('lesson_format', __('billing::plans.formats.private.title'), $this->private)
        ->filterTable('lesson_format', 'group')
        ->assertCanSeeTableRecords([$group])->assertCanNotSeeTableRecords([$this->private]);

    // Someone on it: the cards are shown locked, and a changed format is not taken.
    $student = User::factory()->withRole(Role::Student)->create();
    $teacher = User::factory()->withRole(Role::Teacher)->create();
    $quran = app(CatalogApi::class)->createSubject('قرآن', 'Quran');
    $enrollment = app(EnrollmentApi::class)->create(new NewEnrollment($student->id, $quran, $teacher->id, CarbonImmutable::now()));
    $account = app(CreditsApi::class)->openAccount($student->id, 'enrollment', $enrollment);
    app(EnrollmentApi::class)->attachCreditAccount($enrollment, $account);
    app(BillingApi::class)->subscribe(new NewSubscription($enrollment, $student->id, $account, $group->id, $student->id, $this->gulf->id, 'قرآن', 'Quran'));

    Livewire::test(ManagePlans::class)
        ->mountTableAction('edit', $group)->assertMountedActionModalSee(__('billing::plans.hints.format_locked'))
        ->fillForm(groupPlanForm(['lesson_format' => 'private', 'name_en' => 'Group eight']))
        ->callMountedTableAction()->assertHasNoTableActionErrors();
    expect($group->refresh()->only(['lesson_format', 'name_en']))->toBe(['lesson_format' => 'group', 'name_en' => 'Group eight']);
});
