<?php

namespace Modules\Enrollment\Contracts\DTOs;

use Carbon\CarbonImmutable;

final readonly class NewEnrollment
{
    public function __construct(
        public string $studentId,
        public string $subjectId,
        public string $teacherId,
        public CarbonImmutable $startDate,
        public ?string $actorId = null,
    ) {}
}
