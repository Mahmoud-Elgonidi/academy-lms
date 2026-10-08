<?php

namespace Modules\Enrollment\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Enrollment\Contracts\DTOs\NewEnrollment;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Enrollment\Contracts\Events\EnrollmentCreated;
use Modules\Enrollment\Models\Enrollment;
use Modules\Enrollment\Queries\ActiveStudents;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Limit;

/**
 * Opens an enrollment awaiting its first payment, with its first teacher
 * assignment. When the subject has exactly one published program, the
 * student follows it from the start; otherwise someone picks one later.
 * A student who is not active yet needs room in the academy's plan (its
 * active students limit).
 */
final class CreateEnrollment
{
    public function __construct(
        private readonly CurriculumApi $curriculum,
        private readonly AcademyPlan $plan,
        private readonly ActiveStudents $students,
    ) {}

    public function handle(NewEnrollment $new): Enrollment
    {
        $programs = $this->curriculum->programsFor($new->subjectId);
        $program = count($programs) === 1 ? $programs[0] : null;

        return DB::transaction(function () use ($new, $program) {
            if ($this->plan->limit(Limit::ActiveStudents) !== null) {
                // One new student at a time per academy, so two at once cannot both take the last place.
                DB::select("select pg_advisory_xact_lock(hashtext('plan:students:' || app_current_tenant()::text))");
                if (! $this->students->includes($new->studentId)) {
                    $this->plan->ensureRoom(Limit::ActiveStudents, $this->students->count() + 1);
                }
            }

            $enrollment = Enrollment::query()->create([
                'student_id' => $new->studentId,
                'subject_id' => $new->subjectId,
                'teacher_id' => $new->teacherId,
                'status' => EnrollmentStatus::PendingPayment,
                'start_date' => $new->startDate->toDateString(),
                'program_id' => $program?->id,
                'curriculum_version_id' => $program?->publishedVersionId,
                'created_by' => $new->actorId,
            ]);
            $enrollment->logStatus(null, EnrollmentStatus::PendingPayment, 'created', $new->actorId);

            DB::table('enrollment_teacher_assignments')->insert([
                'academy_id' => $enrollment->academy_id,
                'enrollment_id' => $enrollment->id,
                'teacher_id' => $new->teacherId,
                'from_date' => $new->startDate->toDateString(),
                'assigned_by' => $new->actorId,
            ]);

            DB::afterCommit(fn () => event(new EnrollmentCreated($enrollment->academy_id, $enrollment->id, $new->studentId, $new->subjectId, $new->teacherId)));

            return $enrollment;
        });
    }
}
