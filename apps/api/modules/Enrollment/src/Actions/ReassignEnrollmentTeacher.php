<?php

namespace Modules\Enrollment\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Contracts\Events\TeacherReassigned;
use Modules\Enrollment\Models\Enrollment;

/** The enrollment's teacher from a date: the current assignment ends the day before, a new one starts. */
final class ReassignEnrollmentTeacher
{
    public function handle(string $enrollmentId, string $teacherId, CarbonImmutable $from, ?string $actorId): void
    {
        DB::transaction(function () use ($enrollmentId, $teacherId, $from, $actorId) {
            $enrollment = Enrollment::query()->whereKey($enrollmentId)->lockForUpdate()->firstOrFail();
            $previous = $enrollment->teacher_id;

            DB::table('enrollment_teacher_assignments')->where('enrollment_id', $enrollmentId)->whereNull('to_date')
                ->update(['to_date' => $from->subDay()->toDateString()]);
            DB::table('enrollment_teacher_assignments')->insert([
                'academy_id' => $enrollment->academy_id,
                'enrollment_id' => $enrollmentId,
                'teacher_id' => $teacherId,
                'from_date' => $from->toDateString(),
                'assigned_by' => $actorId,
            ]);

            $enrollment->teacher_id = $teacherId;
            $enrollment->save();

            DB::afterCommit(fn () => event(new TeacherReassigned($enrollment->academy_id, $enrollmentId, $previous, $teacherId, $from->toDateString())));
        });
    }
}
