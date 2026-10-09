<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Catalog\Actions\SaveSubject;
use Modules\Catalog\Filament\Subjects\ManageSubjects;
use Modules\Catalog\Models\Subject;
use Modules\Communication\Models\Notification;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Import\Importers\FamiliesImporter;
use Modules\Import\Importers\TeachersImporter;
use Modules\Import\Support\ImportContext;
use Modules\People\Actions\Directory\AddStudent;
use Modules\People\Actions\Directory\AddTeacher;
use Modules\People\Actions\Directory\NewGuardian;
use Modules\People\Actions\Directory\UnlinkGuardian;
use Modules\People\Actions\Directory\UpdateGuardianLink;
use Modules\People\Actions\Directory\UpdateTeacher;
use Modules\People\Contracts\AgeGroup;
use Modules\People\Contracts\DTOs\StudentDetails;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\DTOs\TeacherSubjectData;
use Modules\People\Contracts\PeopleApi;
use Modules\People\Filament\Directory\Students\CreateStudent;
use Modules\People\Filament\Directory\Students\EditStudent;
use Modules\People\Filament\Directory\Students\GuardiansRelationManager;
use Modules\People\Filament\Directory\Teachers\CreateTeacher;
use Modules\People\Filament\Directory\Teachers\EditTeacher;
use Modules\People\Filament\Directory\Teachers\ListTeachers;
use Modules\People\Models\GuardianStudent;
use Modules\People\Models\StudentProfile;
use Modules\People\Models\TeacherProfile;
use Modules\People\Queries\Models\Person;
use Modules\SharedKernel\Audit\AuditActor;

/*
 * The academy's people by hand (docs/architecture/01, sections 3, 5 and 6):
 * subjects with levels and categories; teachers with the ages, levels and
 * categories they teach; students, adults signing in themselves or children
 * under a guardian; guardians and their links; accounts turned off and on.
 */

beforeEach(function () {
    $this->withoutDefer();
    CarbonImmutable::setTestNow('2026-10-05 07:00:00');
    $this->noor = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'default_timezone' => 'Africa/Cairo']));
    $this->owner = User::factory()->withRole(Role::Owner)->create(['name' => 'Hala']);
    $this->quran = app(SaveSubject::class)->handle(null, 'قرآن', 'Quran',
        [['name_ar' => 'القاعدة النورانية', 'name_en' => 'Noorani'], ['name_ar' => 'جزء عم', 'name_en' => 'Juz Amma'], ['name_ar' => 'حفظ متقدم', 'name_en' => 'Advanced']],
        [['name_ar' => 'حفظ', 'name_en' => 'Memorising'], ['name_ar' => 'تجويد', 'name_en' => 'Tajweed']]);
    $this->levels = $this->quran->levels->pluck('id')->all();
    $this->tracks = $this->quran->categories->pluck('id')->all();
    app()->setLocale('en');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    AuditActor::forget();
});

it('keeps a subject\'s levels in order, renames and moves them by id, and holds the ones teachers teach', function () {
    $save = app(SaveSubject::class);
    [$noorani, $amma, $advanced] = $this->levels;

    // Reordered and renamed: the same rows, in the new order.
    $subject = $save->handle($this->quran->id, 'قرآن', 'Quran', [
        ['id' => $amma, 'name_ar' => 'جزء عم', 'name_en' => 'Juz Amma'],
        ['id' => $noorani, 'name_ar' => 'نوراني', 'name_en' => 'Noorani basics'],
        ['id' => $advanced, 'name_ar' => 'حفظ متقدم'],
    ], $this->quran->categories->map(fn ($c) => ['id' => $c->id, 'name_ar' => $c->name_ar, 'name_en' => $c->name_en])->all());
    expect($subject->levels->pluck('id')->all())->toBe([$amma, $noorani, $advanced])
        ->and($subject->levels[1]->name_en)->toBe('Noorani basics')
        ->and($subject->levels[2]->name_en)->toBe('حفظ متقدم');   // no English name: the Arabic one

    // A level a teacher teaches cannot go; one nobody uses can.
    $teacher = User::factory()->withRole(Role::Teacher)->create();
    app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($teacher->id, 'female', subjects: [new TeacherSubjectData($this->quran->id, $amma, $amma)]));
    $without = fn (string $gone) => array_values(array_filter($subject->levels->map(fn ($l) => ['id' => $l->id, 'name_ar' => $l->name_ar])->all(), fn ($l) => $l['id'] !== $gone));
    expect(fn () => $save->handle($this->quran->id, 'قرآن', 'Quran', $without($amma)))->toThrow(ValidationException::class, 'Juz Amma');
    expect($save->handle($this->quran->id, 'قرآن', 'Quran', $without($advanced))->levels)->toHaveCount(2);

    // Names: required, never twice in a list, never a second subject with the same name.
    expect(fn () => $save->handle(null, 'QURAN', null))->toThrow(ValidationException::class)
        ->and(fn () => $save->handle(null, 'عربي', 'Arabic', [['name_ar' => 'أ'], ['name_ar' => 'أ']]))->toThrow(ValidationException::class)
        ->and(app(SaveSubject::class)->handle(null, 'عربي', '')->name_en)->toBe('عربي');
});

it('shows the subjects screen to the owner and admins only', function () {
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->owner);
    Livewire::test(ManageSubjects::class)
        ->assertSee('Quran')->assertSee('Noorani')->assertSee('Tajweed')
        ->callAction('create', data: ['name_ar' => 'إنجليزي', 'name_en' => 'English', 'levels' => [['name_ar' => 'مبتدئ', 'name_en' => 'Beginner']], 'categories' => []])
        ->assertHasNoActionErrors();
    expect(Subject::query()->where('name_en', 'English')->sole()->levels()->pluck('name_en')->all())->toBe(['Beginner']);

    $this->actingAs(User::factory()->withRole(Role::Scheduler)->create());
    $this->get('http://noor.lms.test/admin/subjects')->assertForbidden();
});

it('adds a teacher with their ages, levels and categories, and welcomes them', function () {
    [$noorani, $amma, $advanced] = $this->levels;
    $profile = new TeacherProfileData('', 'female', ['ar', 'en'], [], 'Egyptian', true, false, 600, ['kids', 'middle'], 5,
        [new TeacherSubjectData($this->quran->id, $noorani, $amma, [$this->tracks[1]])]);
    $id = app(AddTeacher::class)->handle(new UserDetails('Sara', 'sara@noor.test', '+201001112223', 'ar', 'Africa/Cairo'), $profile, true);

    $saved = TeacherProfile::query()->findOrFail($id);
    expect($saved->age_groups)->toBe(['kids', 'middle'])->and($saved->max_daily_lessons)->toBe(5)->and($saved->accepts_trials)->toBeFalse()
        ->and((array) DB::table('teacher_subjects')->where('teacher_id', $id)->first(['level_from_id', 'level_to_id']))->toBe(['level_from_id' => $noorani, 'level_to_id' => $amma])
        ->and(DB::table('teacher_subject_categories')->where('teacher_id', $id)->pluck('category_id')->all())->toBe([$this->tracks[1]])
        ->and(Notification::query()->where('user_id', $id)->where('template_key', 'people.welcome_teacher')->sole()->body)->toContain('/welcome/'.$id);

    // The levels run upwards, and are the subject's own.
    $arabic = app(SaveSubject::class)->handle(null, 'عربي', 'Arabic', [['name_ar' => 'أ']]);
    $wrong = fn (TeacherSubjectData $s) => fn () => app(UpdateTeacher::class)->handle($id, new UserDetails('Sara', 'sara@noor.test', '+201001112223'), new TeacherProfileData('', 'female', subjects: [$s]));
    expect($wrong(new TeacherSubjectData($this->quran->id, $advanced, $noorani)))->toThrow(ValidationException::class)
        ->and($wrong(new TeacherSubjectData($this->quran->id, $arabic->levels[0]->id)))->toThrow(ValidationException::class)
        ->and($wrong(new TeacherSubjectData($this->quran->id, null, null, [$arabic->id])))->toThrow(ValidationException::class);

    // An import that names only the subjects keeps the levels and categories the screen gave.
    app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($id, 'female', ['ar'], [$this->quran->id]));
    expect(DB::table('teacher_subjects')->where('teacher_id', $id)->value('level_to_id'))->toBe($amma)
        ->and(TeacherProfile::query()->findOrFail($id)->age_groups)->toBe(['kids', 'middle'])
        ->and(AgeGroup::forAge(9))->toBe(AgeGroup::Kids)->and(AgeGroup::forAge(30))->toBe(AgeGroup::Adults)->and(AgeGroup::forAge(null))->toBeNull();
});

it('adds and edits a teacher from the screen, a number typed the local way included', function () {
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->owner);
    [$noorani, $amma] = $this->levels;

    Livewire::test(CreateTeacher::class)
        ->fillForm([
            'name' => 'Omar', 'email' => 'omar@noor.test', 'phone' => '010 1234 5678', 'timezone' => 'Africa/Cairo', 'locale' => 'ar',
            'gender' => 'male', 'languages' => ['ar'], 'age_groups' => ['adults'], 'max_weekly_hours' => 12.5, 'max_daily_lessons' => 6,
            'subjects' => [['subject_id' => $this->quran->id, 'level_from_id' => $noorani, 'level_to_id' => $amma, 'category_ids' => $this->tracks]],
            'welcome' => false,
        ])
        ->call('create')->assertHasNoFormErrors();
    $omar = User::query()->where('email', 'omar@noor.test')->sole();
    expect($omar->phone_e164)->toBe('+201012345678')->and($omar->hasRole(Role::Teacher))->toBeTrue()
        ->and(TeacherProfile::query()->findOrFail($omar->id)->max_weekly_min)->toBe(750)
        ->and(Notification::query()->where('user_id', $omar->id)->exists())->toBeFalse();

    Livewire::test(EditTeacher::class, ['record' => $omar->id])
        ->assertFormSet(['name' => 'Omar', 'gender' => 'male', 'max_daily_lessons' => 6])
        ->fillForm(['phone' => '+966501234567', 'max_daily_lessons' => 4])
        ->call('save')->assertHasNoFormErrors();
    expect($omar->fresh()->phone_e164)->toBe('+966501234567')->and(TeacherProfile::query()->findOrFail($omar->id)->max_daily_lessons)->toBe(4)
        ->and(DB::table('audit_logs')->where('action', 'identity.contact_changed')->where('subject_id', $omar->id)->value('data'))->not->toContain('966');

    Livewire::test(ListTeachers::class)->assertSee('Omar')->assertSee('Quran');
    $this->get('http://noor.lms.test/admin/people/teachers')->assertOk();
    $this->get('http://noor.lms.test/admin/people/students/'.$omar->id.'/edit')->assertNotFound();   // a teacher is not a student
});

it('adds a child under a new guardian, who is welcomed and asked for consent; an adult signs in themselves', function () {
    $add = app(AddStudent::class);
    $child = $add->handle(new UserDetails('Yousef', null, null, 'ar', 'Asia/Riyadh'),
        new NewGuardian(details: new UserDetails('Khaled', 'khaled@noor.test', '+966501112223', 'ar', 'Asia/Riyadh'), relationship: 'father'),
        CarbonImmutable::parse('2017-03-01'), null, new StudentDetails('male', 'sa', 'g3', 'male_only'), true);
    $khaled = User::query()->where('email', 'khaled@noor.test')->sole();
    $profile = StudentProfile::query()->findOrFail($child);

    expect(User::query()->find($child)->is_managed_minor)->toBeTrue()
        ->and($profile->country)->toBe('SA')->and($profile->school_grade)->toBe('g3')->and($profile->teacher_gender)->toBe('male_only')
        ->and(GuardianStudent::query()->where('student_id', $child)->sole()->relationship)->toBe('father')
        ->and(Notification::query()->where('user_id', $khaled->id)->pluck('template_key')->sort()->values()->all())->toBe(['people.consent_request', 'people.welcome_family'])
        ->and(Notification::query()->where('user_id', $child)->exists())->toBeFalse();

    $adult = $add->handle(new UserDetails('Mariam', 'mariam@noor.test', null), null, CarbonImmutable::parse('1999-01-01'), null, new StudentDetails, true);
    expect(User::query()->find($adult)->is_managed_minor)->toBeFalse()
        ->and(Notification::query()->where('user_id', $adult)->where('template_key', 'people.welcome_family')->exists())->toBeTrue();

    // A child with no way in and no guardian: refused; a guardian who is not one: refused.
    expect(fn () => $add->handle(new UserDetails('Ali'), null, null, null, new StudentDetails, false))->toThrow(ValidationException::class)
        ->and(fn () => $add->handle(new UserDetails('Ali'), new NewGuardian(existingId: $this->owner->id), null, null, new StudentDetails, false))->toThrow(ValidationException::class)
        ->and(fn () => $add->handle(new UserDetails('Ali'), new NewGuardian(existingId: $khaled->id), null, null, new StudentDetails(country: 'Egypt'), false))->toThrow(ValidationException::class);
});

it('adds a student from the screen and looks after their guardians on their page', function () {
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->owner);
    $mother = User::factory()->withRole(Role::Guardian)->create(['name' => 'Huda', 'phone_e164' => '+201000000001']);

    Livewire::test(CreateStudent::class)
        ->fillForm(['name' => 'Laila', 'own_contact' => false, 'timezone' => 'Africa/Cairo', 'birth_date' => '2016-06-01', 'stage' => 'auto',
            'gender' => 'female', 'country' => 'EG', 'teacher_gender' => 'female_only', 'guardian_mode' => 'existing', 'guardian_id' => $mother->id,
            'relationship' => 'mother', 'welcome' => true])
        ->call('create')->assertHasNoFormErrors();
    $laila = User::query()->where('name', 'Laila')->sole();
    expect($laila->is_managed_minor)->toBeTrue()->and(StudentProfile::query()->findOrFail($laila->id)->teacher_gender)->toBe('female_only');

    Livewire::test(EditStudent::class, ['record' => $laila->id])->assertFormSet(['name' => 'Laila', 'country' => 'EG', 'managed' => true]);

    // The only guardian of a child stays; with a second one, the first can go and the second becomes the main one.
    $page = Livewire::test(GuardiansRelationManager::class, ['ownerRecord' => Person::query()->find($laila->id), 'pageClass' => EditStudent::class])
        ->assertSee('Huda');
    expect(fn () => app(UnlinkGuardian::class)->handle($mother->id, $laila->id))->toThrow(ValidationException::class);
    $page->callTableAction('addGuardian', data: ['guardian_mode' => 'new', 'guardian_name' => 'Sameh', 'guardian_phone' => '01001234567', 'guardian_timezone' => 'Africa/Cairo', 'relationship' => 'father', 'welcome' => false])
        ->assertHasNoTableActionErrors();
    $sameh = User::query()->where('name', 'Sameh')->sole();
    expect($sameh->phone_e164)->toBe('+201001234567')->and(GuardianStudent::query()->where('guardian_id', $sameh->id)->sole()->is_primary)->toBeFalse();
    app(UnlinkGuardian::class)->handle($mother->id, $laila->id);
    expect(GuardianStudent::query()->where('student_id', $laila->id)->sole()->is_primary)->toBeTrue();

    app(UpdateGuardianLink::class)->handle($sameh->id, $laila->id, 'father', true, false);
    expect(GuardianStudent::query()->where('student_id', $laila->id)->sole()->receives_reports)->toBeFalse()
        ->and(fn () => app(UpdateGuardianLink::class)->handle($sameh->id, $laila->id, 'uncle', true, true))->toThrow(ValidationException::class);
});

it('reads a teacher\'s ages and daily cap, and a student\'s gender, from a sheet', function () {
    $context = new ImportContext('20', 'Africa/Cairo', 'ar', $this->owner->id);
    $teacher = app(TeachersImporter::class);
    $row = array_fill_keys(array_map(fn ($f) => $f->key, $teacher->fields()), '');
    $outcome = $teacher->import(['name' => 'Nour', 'phone' => '01011112222', 'gender' => 'أنثى', 'subjects' => 'قرآن', 'ages' => 'أطفال و إعدادي', 'max_daily' => '٥'] + $row, $context);
    $profile = TeacherProfile::query()->findOrFail($outcome->refs['teacher']);
    expect($profile->age_groups)->toBe(['kids', 'middle'])->and($profile->max_daily_lessons)->toBe(5)
        ->and(fn () => $teacher->import(['name' => 'Nour', 'phone' => '01011112222', 'gender' => 'أنثى', 'subjects' => 'قرآن', 'ages' => 'شباب'] + $row, $context))->toThrow(ValidationException::class);

    $families = app(FamiliesImporter::class);
    $row = array_fill_keys(array_map(fn ($f) => $f->key, $families->fields()), '');
    $outcome = $families->import(['student_name' => 'Salma', 'gender' => 'بنت', 'guardian_name' => 'Amr', 'guardian_phone' => '01033334444'] + $row, $context);
    expect(StudentProfile::query()->findOrFail($outcome->refs['student'])->gender)->toBe('female');
});
