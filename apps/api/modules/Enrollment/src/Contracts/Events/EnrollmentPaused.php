<?php

namespace Modules\Enrollment\Contracts\Events;

/**
 * A pause was set on a course (or made longer): no lessons from $from to
 * $until, days on the academy's calendar, both included. The course is
 * paused from $from on (now, when that day has come).
 */
final readonly class EnrollmentPaused
{
    public function __construct(
        public string $academyId,
        public string $enrollmentId,
        public string $studentId,
        public string $from,
        public string $until,
        public ?string $actorId = null,
    ) {}
}
