<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Enrollment\Actions\ReassignEnrollmentTeacher;
use Modules\Enrollment\Contracts\DTOs\NewEnrollment;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\Events\TeacherReassigned;
use Modules\Enrollment\Models\Enrollment;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Plans\PlanWarning;
use Modules\SharedKernel\Plans\PlanLimitReached;
use Tests\Support\EnrolledStudent;
use Tests\Support\RivalConnection;

uses(EnrolledStudent::class, RivalConnection::class);

/*
 * Courses (docs/architecture/02, sections 3 and 11): a new student takes a
 * place in the academy's plan, one at a time per academy (an advisory lock),
 * so two coordinators at once cannot both take the last place; and a
 * course's teacher changes from a date, the history kept. The fixture's
 * student is enrolled already (one place taken).
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $plan = SaasPlan::query()->create(['code' => 'two-'.Str::lower(Str::random(4)), 'name_ar' => 'باقة', 'name_en' => 'Two students', 'features' => [], 'max_active_students' => 2]);
    app(ChangeAcademyPlan::class)->handle($this->academy->id, $plan->id, 'For this test', [], [], []);
    app(PlanWarning::class)->forget($this->academy->id);
    $this->newcomer = fn () => User::factory()->withRole(Role::Student)->create()->id;
    $this->enrol = fn (string $student) => app(EnrollmentApi::class)->create(new NewEnrollment($student, $this->quran, $this->teacher->id, CarbonImmutable::now()));
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('waits for another new student being enrolled at the same moment, then counts them', function () {
    $lock = "plan:students:{$this->academy->id}";
    $rival = $this->rival();

    // Another coordinator is enrolling a new student: their transaction holds the academy's place lock.
    $rival->beginTransaction();
    try {
        $rival->select('select pg_advisory_xact_lock(hashtext(?))', [$lock]);
        DB::statement("set lock_timeout = '300ms'");
        expect(fn () => ($this->enrol)(($this->newcomer)()))->toThrow(QueryException::class, 'lock timeout');
    } finally {
        DB::statement('set lock_timeout = 0');
        $rival->rollBack();
    }

    // Theirs commits just before ours takes the lock: ours counts it and finds the plan full.
    $theirs = ($this->newcomer)();
    $this->rivalCommitsBefore('select pg_advisory_xact_lock', fn () => $this->rival()->insert(
        'insert into enrollments (id, student_id, subject_id, teacher_id, start_date, created_at, updated_at) values (?, ?, ?, ?, ?, now(), now())',
        [(string) Str::uuid(), $theirs, $this->quran, $this->teacher->id, '2026-10-05'],
    ));

    expect(fn () => ($this->enrol)(($this->newcomer)()))->toThrow(PlanLimitReached::class)
        ->and(Enrollment::query()->whereIn('status', ['pending_payment', 'active'])->distinct()->count('student_id'))->toBe(2);
});

it('changes a course\'s teacher from a date: the old assignment ends the day before, and the change is told after it is saved', function () {
    Event::fake([TeacherReassigned::class]);

    app(ReassignEnrollmentTeacher::class)->handle($this->booked->enrollmentId, $this->other->id, CarbonImmutable::parse('2026-10-12'), $this->admin->id);

    expect(Enrollment::query()->findOrFail($this->booked->enrollmentId)->teacher_id)->toBe($this->other->id)
        ->and(DB::table('enrollment_teacher_assignments')->where('enrollment_id', $this->booked->enrollmentId)->orderBy('from_date')->get()
            ->map(fn ($a) => [$a->teacher_id, $a->from_date, $a->to_date])->all())->toBe([
                [$this->teacher->id, '2026-10-06', '2026-10-11'],
                [$this->other->id, '2026-10-12', null],
            ]);
    Event::assertDispatched(TeacherReassigned::class, fn ($e) => $e->fromTeacherId === $this->teacher->id && $e->toTeacherId === $this->other->id && $e->fromDate === '2026-10-12');
});
