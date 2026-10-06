<?php

namespace Modules\Enrollment\Contracts\Events;

final readonly class EnrollmentCreated
{
    public function __construct(public string $academyId, public string $enrollmentId, public string $studentId, public string $subjectId, public string $teacherId) {}
}
