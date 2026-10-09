<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Catalog\Actions\SaveSubject;
use Modules\People\Actions\SaveStudentDetails;
use Modules\People\Contracts\DTOs\StudentDetails;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\DTOs\TeacherSubjectData;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Contracts\DTOs\TeacherSearch;
use Modules\Scheduling\Domain\Availability\StudentPreference;
use Modules\Scheduling\Filament\Pages\FindTeacher;
use Modules\Scheduling\Queries\FindTeachersQuery;
use Tests\Support\EnrolledStudent;

/*
 * "Find a teacher" by the profile (docs/architecture/02, section 2): the
 * level within a teacher's range of the subject's levels, the category
 * among the ones they teach it in, the ages they teach; a day that already
 * holds a teacher's daily cap of lessons is not offered; and the student's
 * own profile starts the search.
 */

uses(EnrolledStudent::class);

beforeEach(function () {
    $this->enrolStudentOnPlan();   // the Cairo teacher has a lesson every Tuesday and Saturday at 18:00 Cairo
    $subject = app(SaveSubject::class)->handle($this->quran, 'قرآن', 'Quran',
        [['name_ar' => 'نوراني'], ['name_ar' => 'جزء عم'], ['name_ar' => 'متقدم']], [['name_ar' => 'حفظ'], ['name_ar' => 'تجويد']]);
    [$this->noorani, $this->amma, $this->advanced] = $subject->levels->pluck('id')->all();
    [$this->memorising, $this->tajweed] = $subject->categories->pluck('id')->all();

    $people = app(PeopleApi::class);
    $people->saveTeacherProfile(new TeacherProfileData($this->teacher->id, 'female', ageGroups: ['kids'],
        subjects: [new TeacherSubjectData($this->quran, $this->noorani, $this->amma, [$this->memorising])]));
    $people->saveTeacherProfile(new TeacherProfileData($this->other->id, 'male', ageGroups: [],
        subjects: [new TeacherSubjectData($this->quran, $this->advanced)]));

    // Tuesdays 19:00–22:00 Riyadh (18:00–21:00 Cairo), one lesson a week, from the 6th, two weeks.
    $pref = new StudentPreference('Asia/Riyadh', StudentPreference::windowsFromHours([2 => [19, 20, 21]]), 1, 30, CarbonImmutable::parse('2026-10-06'), 2, 1);
    $this->find = fn (...$filters) => collect(app(FindTeachersQuery::class)->run(new TeacherSearch($this->quran, $pref, ...$filters)))
        ->pluck('teacher.id')->sort()->values()->all();
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('finds teachers by level, category and age', function () {
    $both = collect([$this->teacher->id, $this->other->id])->sort()->values()->all();

    expect(($this->find)())->toBe($both)
        ->and(($this->find)(levelId: $this->amma))->toBe([$this->teacher->id])
        ->and(($this->find)(levelId: $this->advanced))->toBe([$this->other->id])       // open upwards
        ->and(($this->find)(categoryId: $this->memorising))->toBe($both)               // the other teaches every category
        ->and(($this->find)(categoryId: $this->tajweed))->toBe([$this->other->id])
        ->and(($this->find)(ageGroup: 'adults'))->toBe([$this->other->id])             // the other teaches any age
        ->and(($this->find)(ageGroup: 'kids', levelId: $this->noorani, categoryId: $this->memorising))->toBe([$this->teacher->id]);
});

it('offers no day that already holds the teacher\'s daily cap', function () {
    $cap = fn (?int $n) => app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($this->teacher->id, 'female', maxDailyLessons: $n,
        subjects: [new TeacherSubjectData($this->quran)]));

    $cap(1);   // Tuesday already has its one lesson
    expect(($this->find)())->toBe([$this->other->id]);
    $cap(2);
    expect(($this->find)())->toContain($this->teacher->id);
});

it('starts from the student\'s own profile', function () {
    app(SaveStudentDetails::class)->handle($this->student->id, new StudentDetails(teacherGender: 'female_only'));
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');

    Livewire::test(FindTeacher::class)
        ->fillForm(['student_id' => $this->student->id])
        ->assertSet('data.gender', 'female_only')
        ->assertSet('data.age_group', 'adults')     // born 2004
        ->fillForm(['subject_id' => $this->quran])
        ->assertSee('جزء عم');
});
