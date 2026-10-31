<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Catalog\Actions\SaveSubject;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\People\Filament\Directory\Teachers\EditTeacher;
use Modules\People\Filament\Directory\Teachers\ListTeachers;
use Modules\People\Models\TeacherProfile;
use Modules\People\Queries\Models\Person;

/*
 * Teachers › teaching for group lessons (docs/architecture/02, "الحصص
 * الجماعية"): "Teaches groups" reveals the most students one lesson holds
 * (2–20, said as it moves), saved through SaveTeacherProfile; the table
 * marks who teaches groups and filters by it.
 */

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-05 07:00:00');
    $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'default_timezone' => 'Africa/Cairo']));
    $this->owner = User::factory()->withRole(Role::Owner)->create();
    $this->quran = app(SaveSubject::class)->handle(null, 'قرآن', 'Quran');
    $this->teacher = User::factory()->withRole(Role::Teacher)->create(['name' => 'Huda']);
    $this->other = User::factory()->withRole(Role::Teacher)->create(['name' => 'Omar']);
    foreach ([$this->teacher, $this->other] as $t) {
        app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($t->id, 'female', ['ar'], [$this->quran->id]));
    }
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->owner);
    app()->setLocale('ar');
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('reveals the group size with "teaches groups", says it in words, and saves both', function () {
    $page = Livewire::test(EditTeacher::class, ['record' => $this->teacher->id])
        ->assertFormSet(['accepts_groups' => false])
        ->assertFormFieldIsHidden('max_group_size')
        ->fillForm(['accepts_groups' => true])
        ->assertFormFieldIsVisible('max_group_size')
        ->fillForm(['max_group_size' => 6])
        ->assertSee(trans_choice('people::directory.fields.group_up_to', 6, ['n' => 6]))
        ->assertSee(__('people::directory.fields.groups_note'));
    $page->call('save')->assertHasNoFormErrors();
    expect(TeacherProfile::query()->findOrFail($this->teacher->id)->only(['accepts_groups', 'max_group_size']))->toBe(['accepts_groups' => true, 'max_group_size' => 6]);

    // Off again: no size kept.
    Livewire::test(EditTeacher::class, ['record' => $this->teacher->id])->assertFormSet(['accepts_groups' => true, 'max_group_size' => 6])
        ->fillForm(['accepts_groups' => false])->call('save')->assertHasNoFormErrors();
    expect(TeacherProfile::query()->findOrFail($this->teacher->id)->only(['accepts_groups', 'max_group_size']))->toBe(['accepts_groups' => false, 'max_group_size' => null]);
});

it('marks who teaches groups in the table and filters by it', function () {
    app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($this->teacher->id, 'female', ['ar'], [$this->quran->id], acceptsGroups: true, maxGroupSize: 5));
    [$huda, $omar] = [Person::query()->findOrFail($this->teacher->id), Person::query()->findOrFail($this->other->id)];

    Livewire::test(ListTeachers::class)
        ->assertTableColumnStateSet('accepts_groups', true, $huda)
        ->assertTableColumnStateSet('accepts_groups', false, $omar)
        ->filterTable('groups', true)
        ->assertCanSeeTableRecords([$huda])->assertCanNotSeeTableRecords([$omar])
        ->filterTable('groups', false)
        ->assertCanSeeTableRecords([$omar])->assertCanNotSeeTableRecords([$huda]);
});
