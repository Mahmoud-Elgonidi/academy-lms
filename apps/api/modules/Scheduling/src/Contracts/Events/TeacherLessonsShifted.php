<?php

namespace Modules\Scheduling\Contracts\Events;

/**
 * Every weekly lesson of a teacher moved by the same minutes for some days
 * (bulk rescheduling); the teacher hears it once, each family through
 * SlotTimesShifted.
 */
final readonly class TeacherLessonsShifted
{
    public function __construct(
        public string $academyId,
        public string $teacherId,
        public string $firstDay,
        public string $lastDay,
        public int $minutes,
        public int $lessons,
        public ?string $actorId,
    ) {}
}
