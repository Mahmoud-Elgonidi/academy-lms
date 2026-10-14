<?php

namespace Modules\Enrollment\Contracts\Events;

/**
 * A course ends from $on (its first day without lessons, a day on the
 * academy's calendar): ended now when that day has come, else on it. An
 * ended course never comes back; a new enrollment is the way back.
 */
final readonly class EnrollmentEnded
{
    public function __construct(
        public string $academyId,
        public string $enrollmentId,
        public string $studentId,
        public string $on,
        public ?string $actorId = null,
    ) {}
}
