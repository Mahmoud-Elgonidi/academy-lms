<?php

use Illuminate\Support\Facades\Gate;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Models\SaasPlan;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Plans\Feature;

/*
 * Who may do what (docs/architecture/01, "مصفوفة الصلاحيات"; Identity's
 * AbilityMatrix), written out in full: every ability for every role, so a
 * change to who can do what is made here on purpose, never by accident.
 * A new ability fails this test until it has its line. Then the areas a
 * plan may leave out (Ability::feature()): out of the plan, nobody holds
 * them; and a disabled account holds nothing.
 */

/** @return array<string, list<string>> ability => the roles that hold it */
function expectedAbilities(): array
{
    [$o, $a, $su, $sc, $ac, $sa, $t] = ['owner', 'admin', 'supervisor', 'scheduler', 'accountant', 'sales', 'teacher'];

    return [
        'academy.manage-branding' => [$o, $a],
        'live-board.view' => [$o, $a, $su, $sc],
        'sessions.manage' => [$o, $a, $su, $sc],
        'shifts.review' => [$o, $a, $su, $sc],
        'shifts.propose-own' => [$t],
        'teachers.find' => [$o, $a, $su, $sc, $sa],
        'enrollments.view' => [$o, $a, $su, $sc, $ac],
        'billing.view' => [$o, $a, $ac],
        'billing.record-payments' => [$o, $a, $ac],
        'billing.manage' => [$o, $ac],
        'policies.manage' => [$o, $a],
        'curriculum.manage' => [$o, $a, $su],
        'reports.write' => [$t],
        'reports.review' => [$o, $a, $su],
        'messages.view-log' => [$o, $a, $su],
        'messages.manage-templates' => [$o, $a],
        'classroom.manage' => [$o, $a],
        'recordings.view' => [$o, $a, $su],
        'recordings.manage' => [$o, $a],
        'time-off.manage' => [$o, $a, $su, $sc],
        'time-off.request-own' => [$t],
        'holidays.manage' => [$o, $a],
        'leads.manage' => [$o, $a, $sc, $sa],
        'trials.book' => [$o, $a, $su, $sc, $sa],
        'trials.assess' => [$t],
        'performance.review' => [$o, $a, $su],
        'performance.view-own' => [$t],
        'payroll.manage' => [$o, $ac],
        'payroll.view-own' => [$t],
        'data.import' => [$o, $a],
        'audit.view' => [$o, $a],
        'consents.manage' => [$o, $a, $su],
        'academy.view-plan' => [$o, $a],
        'tickets.handle' => [$o, $a, $su, $sc, $ac, $sa],
        'complaints.decide' => [$o, $a, $su],
        'support.manage' => [$o, $a],
        'catalog.manage' => [$o, $a],
        'teachers.manage' => [$o, $a, $sc],
        'students.manage' => [$o, $a, $su, $sc],
        'privacy.handle' => [$o, $a],
        'safeguarding.handle' => [$o, $a],
        'conversations.oversee' => [$o, $a, $su],
        'conversations.manage' => [$o, $a],
    ];
}

/** The areas a plan may leave out, and the abilities that close with each. */
const ABILITY_PLAN_AREAS = [
    'leads.manage' => 'crm', 'trials.book' => 'crm', 'trials.assess' => 'crm',
    'payroll.manage' => 'payroll', 'payroll.view-own' => 'payroll',
    'data.import' => 'import',
];

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->people = collect(Role::cases())->mapWithKeys(fn (Role $role) => [$role->value => User::factory()->withRole($role)->create()])->all();
});

/** @return array<string, list<string>> ability => the roles that hold it now */
function heldAbilities(array $people): array
{
    $held = [];
    foreach (Ability::cases() as $ability) {
        $held[$ability->value] = array_values(array_keys(array_filter($people, fn (User $user) => Gate::forUser($user)->allows($ability->value))));
    }

    return $held;
}

it('gives every role exactly the abilities written out here', function () {
    $expected = expectedAbilities();

    expect(array_keys($expected))->toEqualCanonicalizing(array_column(Ability::cases(), 'value'));
    foreach (heldAbilities($this->people) as $ability => $roles) {
        expect($roles)->toEqualCanonicalizing($expected[$ability], "who holds {$ability}");
    }
    // Families never hold a back-office ability.
    expect(array_filter($expected, fn (array $roles) => array_intersect($roles, ['student', 'guardian']) !== []))->toBe([]);
});

it('closes the areas a plan leaves out for everyone, and only those', function () {
    expect(collect(Ability::cases())->mapWithKeys(fn (Ability $a) => [$a->value => $a->feature()?->value])->filter()->all())->toBe(ABILITY_PLAN_AREAS);

    $this->clearTenant();
    $plan = SaasPlan::query()->create(['code' => 'bare', 'name_ar' => 'باقة', 'name_en' => 'Bare', 'features' => []]);
    app(ChangeAcademyPlan::class)->handle($this->academy->id, $plan->id, 'For this test');
    $this->actAsTenant($this->academy);

    $expected = expectedAbilities();
    foreach (heldAbilities($this->people) as $ability => $roles) {
        expect($roles)->toEqualCanonicalizing(isset(ABILITY_PLAN_AREAS[$ability]) ? [] : $expected[$ability], "who holds {$ability} without its area");
    }

    // Each area opens with the plan, for the same roles as before.
    $this->clearTenant();
    $full = SaasPlan::query()->create(['code' => 'full', 'name_ar' => 'كاملة', 'name_en' => 'Full', 'features' => array_column(Feature::cases(), 'value')]);
    app(ChangeAcademyPlan::class)->handle($this->academy->id, $full->id, 'For this test');
    $this->actAsTenant($this->academy);
    foreach (heldAbilities($this->people) as $ability => $roles) {
        expect($roles)->toEqualCanonicalizing($expected[$ability], "who holds {$ability} with every area");
    }
});

it('gives a disabled account nothing', function () {
    foreach ($this->people as $user) {
        $user->forceFill(['status' => 'disabled'])->save();
    }

    expect(array_filter(heldAbilities($this->people)))->toBe([]);
});
