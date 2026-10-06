<?php

namespace Modules\Scheduling\Contracts\Events;

/**
 * A weekly time moved for a period (bulk rescheduling, or the DST planner
 * keeping the student's time); the teacher hears it, and the family when
 * their time changes.
 */
final readonly class SlotTimesShifted
{
    public function __construct(
        public string $academyId,
        public string $slotId,
        public string $enrollmentId,
        public string $studentId,
        public string $teacherId,
        public string $fromUtc,
        public ?string $untilUtc,
        public int $minutes,
        public int $lessons,
        public string $reason,
        public ?string $actorId,
    ) {}
}
