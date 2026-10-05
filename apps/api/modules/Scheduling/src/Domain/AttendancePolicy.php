<?php

namespace Modules\Scheduling\Domain;

/**
 * The attendance numbers from the default policy
 * (docs/architecture/02-domain-rules.md, section 4). Per-academy policy sets
 * replace defaults() when the policy engine lands.
 */
final readonly class AttendancePolicy
{
    public function __construct(
        public int $teacherLateAfterMin = 2,
        public int $teacherAbsentAfterMin = 10,
        public int $studentNudgeAfterMin = 3,
        public int $studentWaitShortMin = 10,
        public int $studentWaitLongMin = 15,
        public float $completionShare = 0.75,
        public int $weakConnectionDrops = 3,
        public int $weakConnectionWindowMin = 15,
        public int $finalizeAfterEndMin = 10,
    ) {}

    public static function defaults(): self
    {
        return new self;
    }

    /** How long the teacher waits for the student: 10 minutes for up to 30-minute sessions, else 15. */
    public function studentWaitMin(int $durationMin): int
    {
        return $durationMin <= 30 ? $this->studentWaitShortMin : $this->studentWaitLongMin;
    }
}
