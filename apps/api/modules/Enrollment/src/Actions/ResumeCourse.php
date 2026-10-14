<?php

namespace Modules\Enrollment\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Enrollment\Contracts\Events\EnrollmentResumed;
use Modules\Enrollment\Models\Enrollment;
use Modules\Enrollment\Queries\ActiveStudents;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Limit;

/**
 * A paused course is on again: on the day after its pause (by itself), or
 * earlier when the office says so; a pause still ahead is called off. The
 * pause is cleared; the lessons from today are the caller's to make. A
 * paused student is not an active one, so the office's early return needs
 * room in the academy's plan, as a new course does (CreateEnrollment); the
 * return on the day a pause was set to end comes back as planned.
 */
final class ResumeCourse
{
    public function __construct(
        private readonly AcademyPlan $plan,
        private readonly ActiveStudents $students,
    ) {}

    /** @return EnrollmentData the course as it was before */
    public function handle(string $enrollmentId, bool $early, ?string $actorId): EnrollmentData
    {
        return DB::transaction(function () use ($enrollmentId, $early, $actorId) {
            $enrollment = Enrollment::query()->whereKey($enrollmentId)->lockForUpdate()->firstOrFail();
            $before = $enrollment->toData();
            if ($enrollment->status === EnrollmentStatus::Ended || ($enrollment->paused_from === null && $enrollment->status !== EnrollmentStatus::Paused)) {
                throw ValidationException::withMessages(['enrollment' => __('enrollment::enrollment.errors.not_paused')]);
            }

            if ($early && $enrollment->status === EnrollmentStatus::Paused && $this->plan->limit(Limit::ActiveStudents) !== null) {
                DB::select("select pg_advisory_xact_lock(hashtext('plan:students:' || app_current_tenant()::text))");
                if (! $this->students->includes($enrollment->student_id)) {
                    $this->plan->ensureRoom(Limit::ActiveStudents, $this->students->count() + 1);
                }
            }

            $enrollment->clearPause();
            $enrollment->save();
            if ($enrollment->status === EnrollmentStatus::Paused) {
                $enrollment->moveTo(EnrollmentStatus::Active, $early ? 'resume_early' : 'resume', $actorId);
            }

            DB::afterCommit(fn () => event(new EnrollmentResumed($enrollment->academy_id, $enrollment->id, $enrollment->student_id, $early, $actorId)));

            return $before;
        });
    }
}
