<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Identity\Actions\CreateUser;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\Identity\Contracts\SignInGate;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Actions\Directory\AddGuardian;
use Modules\People\Actions\Directory\AddStudent;
use Modules\People\Actions\Directory\NewGuardian;
use Modules\People\Contracts\DTOs\StudentDetails;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\People\Filament\Directory\Guardians\GuardianResource;
use Modules\People\Filament\Directory\Students\CreateStudent;
use Modules\People\Filament\Directory\Students\StudentResource;
use Modules\People\Filament\Pages\GuardianConsents;
use Modules\People\Models\GuardianConsent;
use Modules\People\Models\GuardianStudent;
use Modules\People\Models\PeopleSettings;
use Modules\People\Models\StudentProfile;
use Modules\People\Queries\TeacherChecksBoard;
use Modules\SharedKernel\Audit\AuditActor;

/*
 * People in an academy that trains adults only (docs/architecture/02, section
 * 29): nobody has a guardian or waits for one's consent, every trainee signs
 * in themselves, everyone has the plain look, no rule for under-18s, and the
 * screens speak of trainers and trainees. A families academy beside it keeps
 * all of it.
 */

beforeEach(function () {
    $this->withoutDefer();
    CarbonImmutable::setTestNow('2026-10-05 07:00:00');
    $this->families = $this->createAcademy(['slug' => 'noor']);
    $this->adults = $this->actAsTenant($this->createAcademy(['slug' => 'hm-trust', 'audience' => 'adults']));
    $this->owner = User::factory()->withRole(Role::Owner)->create();
    app()->setLocale('ar');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    AuditActor::forget();
});

/** A student of the current academy, ten years old, under a guardian (made directly, as data from before). */
function youngStudent(): User
{
    $student = User::factory()->withRole(Role::Student)->create(['is_managed_minor' => true, 'email' => null, 'username' => 'learner.'.bin2hex(random_bytes(4))]);
    StudentProfile::query()->create(['user_id' => $student->id, 'birth_date' => '2016-06-01']);

    return $student;
}

it('asks nobody for a guardian\'s consent and gives everyone the plain look', function () {
    $people = app(PeopleApi::class);
    $student = youngStudent();
    $managed = User::factory()->withRole(Role::Student)->create(['is_managed_minor' => true]);

    expect($people->consentState($student->id)->needed)->toBeFalse()
        ->and($people->consentState($managed->id)->needed)->toBeFalse()
        ->and(app(SignInGate::class)->refusal($student->id))->toBeNull()
        ->and($people->studentProfile($student->id)->stage)->toBe(LearnerStage::Classic)
        ->and($people->stages([$student->id]))->toBe([$student->id => LearnerStage::Classic])
        // A minor still by the birth date given (safeguarding); a managed account with none is an adult here.
        ->and($people->isMinor($student->id))->toBeTrue()
        ->and($people->isMinor($managed->id))->toBeFalse();

    // The families academy: the same child waits for consent in the kids' look.
    $this->actAsTenant($this->families);
    $child = youngStudent();
    expect($people->consentState($child->id)->needed)->toBeTrue()
        ->and(app(SignInGate::class)->refusal($child->id)?->type)->toBe('guardian-consent-required')
        ->and($people->studentProfile($child->id)->stage)->toBe(LearnerStage::Kids)
        ->and($people->isMinor(User::factory()->withRole(Role::Student)->create(['is_managed_minor' => true])->id))->toBeTrue();
});

it('links nobody to a guardian and adds trainees who sign in themselves', function () {
    $who = fn (?string $email) => new UserDetails('Karim Adel', $email, null, 'ar', 'Africa/Cairo');
    $student = User::factory()->withRole(Role::Student)->create();
    $guardian = User::factory()->withRole(Role::Guardian)->create();

    expect(fn () => app(PeopleApi::class)->linkGuardian($guardian->id, $student->id))
        ->toThrow(ValidationException::class, __('people::directory.errors.adults_no_guardians'))
        ->and(fn () => app(AddGuardian::class)->handle(new UserDetails('Huda', 'huda@example.com', null, 'ar', 'Africa/Cairo'), false))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(AddStudent::class)->handle($who(null), new NewGuardian(existingId: $guardian->id), null, null, new StudentDetails, false))
        ->toThrow(ValidationException::class, __('people::directory.errors.adults_no_guardians'))
        ->and(fn () => app(AddStudent::class)->handle($who(null), null, null, null, new StudentDetails, false))
        ->toThrow(ValidationException::class, 'اكتب بريد المتدرّب الإلكتروني أو رقمه: يدخل كل متدرّب حسابه بنفسه.')
        // Identity's one door takes no guardian and no account under someone else.
        ->and(fn () => app(CreateUser::class)->handle(new NewUser(name: 'Huda', roles: [Role::Guardian], email: 'huda2@example.com')))
        ->toThrow(ValidationException::class, __('identity::users.adults_only'))
        ->and(fn () => app(CreateUser::class)->handle(new NewUser(name: 'Kid', roles: [Role::Student], username: 'learner.kid1', isManagedMinor: true)))
        ->toThrow(ValidationException::class);
    expect(GuardianStudent::query()->count())->toBe(0);

    $id = app(AddStudent::class)->handle($who('karim@example.com'), null, null, null, new StudentDetails, false);
    expect(User::query()->findOrFail($id)->is_managed_minor)->toBeFalse();

    // The families academy still adds a child under a new guardian.
    $this->actAsTenant($this->families);
    $child = app(AddStudent::class)->handle($who(null), new NewGuardian(details: new UserDetails('Huda', 'huda@example.com', null, 'ar', 'Africa/Cairo')), null, null, new StudentDetails, false);
    expect(User::query()->findOrFail($child)->is_managed_minor)->toBeTrue()->and(GuardianStudent::query()->where('student_id', $child)->exists())->toBeTrue();
});

it('has no guardian screens, and a trainee\'s form with their own contact and no stage, grade or guardian', function () {
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->owner);

    expect(GuardianResource::canAccess())->toBeFalse()
        ->and(GuardianConsents::canAccess())->toBeFalse()
        ->and(StudentResource::getRelations())->toBe([])
        ->and(StudentResource::getNavigationLabel())->toBe('المتدرّبون');

    Livewire::test(CreateStudent::class)
        ->assertFormFieldHidden('own_contact')->assertFormFieldHidden('stage')->assertFormFieldHidden('school_grade')->assertFormFieldHidden('guardian_mode')
        ->assertFormFieldVisible('email')
        ->fillForm(['name' => 'Mona Fathy', 'email' => 'mona@example.com', 'timezone' => 'Africa/Cairo', 'gender' => 'female', 'welcome' => false])
        ->call('create')->assertHasNoFormErrors();
    $mona = User::query()->where('name', 'Mona Fathy')->sole();
    expect($mona->is_managed_minor)->toBeFalse()->and($mona->email)->toBe('mona@example.com');

    $this->actAsTenant($this->families);
    $owner = User::factory()->withRole(Role::Owner)->create();
    $this->actingAs($owner);
    expect(GuardianResource::canAccess())->toBeTrue()->and(GuardianConsents::canAccess())->toBeTrue()
        ->and(StudentResource::getNavigationLabel())->toBe('الطلاب');
    Livewire::test(CreateStudent::class)->assertFormFieldVisible('own_contact')->assertFormFieldVisible('stage')->assertFormFieldVisible('guardian_mode');
});

it('answers 404 to adding a child', function () {
    $guardian = User::factory()->withRole(Role::Guardian)->create();

    $this->withToken($guardian->createToken('phone')->plainTextToken)->withHeaders(['Idempotency-Key' => 'k-adult-child'])
        ->postJson('http://hm-trust.lms.test/api/v1/family/children', ['name' => 'Yara', 'birth_year' => 2018, 'agree' => true, 'version' => GuardianConsent::VERSION])
        ->assertNotFound();
    $this->actAsTenant($this->adults);
    expect(User::query()->where('name', 'Yara')->exists())->toBeFalse();
});

it('asks for no criminal record check before under-18s, and keeps the documents', function () {
    PeopleSettings::current()->forceFill(['require_minor_check' => true])->save();
    $teacher = User::factory()->withRole(Role::Teacher)->create();
    DB::table('teacher_profiles')->insert(['academy_id' => $this->adults->id, 'user_id' => $teacher->id, 'created_at' => now(), 'updated_at' => now()]);

    $board = app(TeacherChecksBoard::class)->forAcademy();
    expect(app(PeopleApi::class)->clearances([$teacher->id])[$teacher->id]->state)->toBe('not_required')
        ->and($board['minors'])->toBeFalse()
        ->and(__('people::checks.office.lead'))->not->toContain('18');

    $this->actAsTenant($this->families);
    PeopleSettings::current()->forceFill(['require_minor_check' => true])->save();
    $other = User::factory()->withRole(Role::Teacher)->create();
    expect(app(PeopleApi::class)->clearances([$other->id])[$other->id]->state)->toBe('missing')
        ->and(app(TeacherChecksBoard::class)->forAcademy()['minors'])->toBeTrue();
});

it('speaks of trainers, trainees and sessions on the people screens', function () {
    app()->setLocale('ar');
    expect(__('people::directory.teachers.nav'))->toBe('المدرّبون')
        ->and(__('people::directory.options.student_genders'))->toBe(['female' => 'أنثى', 'male' => 'ذكر'])
        ->and(__('identity::users.roles.student'))->toBe('متدرّب')
        ->and(__('identity::team.subtitle'))->not->toContain('أولياء الأمور');
    app()->setLocale('en');
    expect(__('people::directory.students.create'))->toBe('New trainee');

    $this->actAsTenant($this->families);
    expect(__('people::directory.students.create'))->toBe('New student');
});
