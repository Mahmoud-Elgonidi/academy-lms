<?php

namespace Modules\Scheduling\Contracts\Events;

/**
 * A free trial lesson was booked (Find a teacher, trial mode). Scheduling
 * tells the family (with each person's link to the class) and the teacher;
 * the CRM ties it to the lead whose learner it is.
 */
final readonly class TrialLessonBooked
{
    public function __construct(
        public string $academyId,
        public string $sessionId,
        public string $studentId,
        public string $teacherId,
        public string $subjectId,
        public string $startsAtUtc,
        public ?string $actorId,
    ) {}
}
