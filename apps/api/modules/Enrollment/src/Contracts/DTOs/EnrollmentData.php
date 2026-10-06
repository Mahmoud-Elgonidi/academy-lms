<?php

namespace Modules\Enrollment\Contracts\DTOs;

use Carbon\CarbonImmutable;
use Modules\Enrollment\Contracts\EnrollmentStatus;

final readonly class EnrollmentData
{
    public function __construct(
        public string $id,
        public string $studentId,
        public string $subjectId,
        public string $teacherId,
        public EnrollmentStatus $status,
        public CarbonImmutable $startDate,
        public ?string $creditAccountId,
        public ?string $programId = null,
        public ?string $curriculumVersionId = null,
    ) {}
}
