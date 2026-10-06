<?php

namespace Modules\Enrollment\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Contracts\DTOs\NewEnrollment;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Enrollment\Contracts\Events\EnrollmentCreated;
use Modules\Enrollment\Models\Enrollment;

/** Opens an enrollment awaiting its first payment, with its first teacher assignment. */
final class CreateEnrollment
{
    public function handle(NewEnrollment $new): Enrollment
    {
        return DB::transaction(function () use ($new) {
            $enrollment = Enrollment::query()->create([
                'student_id' => $new->studentId,
                'subject_id' => $new->subjectId,
                'teacher_id' => $new->teacherId,
                'status' => EnrollmentStatus::PendingPayment,
                'start_date' => $new->startDate->toDateString(),
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
