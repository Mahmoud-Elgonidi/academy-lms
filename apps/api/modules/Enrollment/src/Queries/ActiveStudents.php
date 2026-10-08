<?php

namespace Modules\Enrollment\Queries;

use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Enrollment\Models\Enrollment;

/**
 * The current academy's active students: those with an enrollment that is
 * running or waiting for its first payment. What its plan's student limit
 * counts; a second subject for the same student is not another student.
 */
final class ActiveStudents
{
    public const STATUSES = [EnrollmentStatus::PendingPayment, EnrollmentStatus::Active];

    public function count(): int
    {
        return Enrollment::query()->whereIn('status', self::STATUSES)->distinct()->count('student_id');
    }

    public function includes(string $studentId): bool
    {
        return Enrollment::query()->where('student_id', $studentId)->whereIn('status', self::STATUSES)->exists();
    }
}
