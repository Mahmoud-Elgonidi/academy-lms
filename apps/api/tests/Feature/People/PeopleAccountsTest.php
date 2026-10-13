<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Actions\Directory\SetPersonActive;
use Modules\SharedKernel\Audit\AuditActor;
use Tests\Support\EnrolledStudent;

/*
 * Turning a teacher's, student's or guardian's account off from their page
 * (signed out everywhere, audited) and on again, never while it would strand
 * lessons; and who may manage teachers and families at all.
 */

uses(EnrolledStudent::class);

afterEach(fn () => AuditActor::forget());

it('turns an account off and on, never while it would strand lessons, and keeps who can manage people to their roles', function () {
    $this->enrolStudentOnPlan();   // a teacher, an adult student and their guardian, with lessons ahead
    $this->actingAs($this->admin);
    $off = app(SetPersonActive::class);
    $guardian = DB::table('guardian_student')->where('student_id', $this->student->id)->value('guardian_id');

    expect(fn () => $off->handle($this->teacher->id, false))->toThrow(ValidationException::class, __('people::directory.errors.teacher_busy'))
        ->and(fn () => $off->handle($this->student->id, false))->toThrow(ValidationException::class)
        ->and(fn () => $off->handle($this->other->id, true))->not->toThrow(Exception::class);

    $off->handle($guardian, false);   // the student signs in themselves: their guardian may go
    $this->other->createToken('phone');
    $off->handle($this->other->id, false);
    expect(User::query()->find($this->other->id)->isActive())->toBeFalse()
        ->and($this->other->tokens()->count())->toBe(0)
        ->and(DB::table('audit_logs')->where('action', 'identity.account_disabled')->count())->toBe(2);
    $off->handle($this->other->id, true);
    expect(User::query()->find($this->other->id)->isActive())->toBeTrue();
    expect(fn () => $off->handle($this->admin->id, false))->toThrow(ValidationException::class);   // not oneself

    Filament::setCurrentPanel('academy');
    foreach ([[Role::Scheduler, 200, 200], [Role::Supervisor, 403, 200], [Role::Accountant, 403, 403], [Role::Sales, 403, 403]] as [$role, $teachers, $students]) {
        $this->actAsTenant($this->academy);   // a request ends the one before
        $this->flushSession();               // and its session belongs to the last person
        $this->actingAs(User::factory()->withRole($role)->create());
        $this->get('http://noor.lms.test/admin/people/teachers')->assertStatus($teachers);
        $this->get('http://noor.lms.test/admin/people/students')->assertStatus($students);
    }
});

it('keeps people screens off an account that holds a back-office role above the one acting', function () {
    $this->enrolStudentOnPlan();
    $teacherAdmin = User::factory()->withRole(Role::Teacher)->create();
    $teacherAdmin->assignRole(Role::Admin);
    $plain = User::factory()->withRole(Role::Teacher)->create();
    $off = app(SetPersonActive::class);

    $this->actingAs(User::factory()->withRole(Role::Scheduler)->create());
    expect(fn () => $off->handle($teacherAdmin->id, false))->toThrow(ValidationException::class, __('identity::users.above_you'));
    $off->handle($plain->id, false);   // a teacher with nothing else may go

    $this->actingAs($this->admin);     // someone of that rank may
    $off->handle($teacherAdmin->id, false);
    expect(User::query()->find($teacherAdmin->id)->isActive())->toBeFalse()
        ->and(User::query()->find($plain->id)->isActive())->toBeFalse();
});
