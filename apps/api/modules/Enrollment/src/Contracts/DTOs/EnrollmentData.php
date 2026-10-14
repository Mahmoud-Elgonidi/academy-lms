<?php

namespace Modules\Enrollment\Contracts\DTOs;

use Carbon\CarbonImmutable;
use Modules\Enrollment\Contracts\EnrollmentStatus;

/**
 * A course. Its pause (first and last day, both included) and its end (the
 * first day without lessons) are days on the academy's calendar; a pause
 * may lie ahead while the course is still active, and so may an end.
 */
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
        public ?CarbonImmutable $pausedFrom = null,
        public ?CarbonImmutable $pausedUntil = null,
        public ?CarbonImmutable $endsOn = null,
    ) {}

    /** Is this day ("Y-m-d" on the academy's calendar) one of its paused days? */
    public function pausedOn(string $day): bool
    {
        return $this->pausedFrom !== null && $this->pausedUntil !== null
            && $day >= $this->pausedFrom->toDateString() && $day <= $this->pausedUntil->toDateString();
    }

    /** Is the course over by this day ("Y-m-d" on the academy's calendar)? */
    public function endedBy(string $day): bool
    {
        return $this->status === EnrollmentStatus::Ended || ($this->endsOn !== null && $day >= $this->endsOn->toDateString());
    }
}
