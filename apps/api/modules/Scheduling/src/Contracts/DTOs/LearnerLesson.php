<?php

namespace Modules\Scheduling\Contracts\DTOs;

use Carbon\CarbonImmutable;

/** One of a student's lessons as their challenges and journey count it: its outcome, when the student first came in, and how long they stayed. */
final readonly class LearnerLesson
{
    public function __construct(
        public string $id,
        /** scheduled, in_progress, completed, student_absent, teacher_absent, needs_review, cancelled */
        public string $status,
        public CarbonImmutable $startsAt,
        public ?CarbonImmutable $joinedAt,
        public ?string $enrollmentId,
        public ?CarbonImmutable $endsAt = null,
        /** When the student last left the room (null: never left, or never came). */
        public ?CarbonImmutable $lastLeftAt = null,
        /** Minutes the student was in the room, counted when they left. */
        public int $attendedMin = 0,
    ) {}

    /** Lessons the student could not have attended (cancelled, or the teacher's absence) count for nothing. */
    public function counts(): bool
    {
        return ! in_array($this->status, ['cancelled', 'teacher_absent'], true);
    }

    public function attended(): bool
    {
        return $this->status === 'completed';
    }

    public function missed(): bool
    {
        return $this->status === 'student_absent';
    }

    /** Attended, having first come in no later than $graceMin after the start. */
    public function onTime(int $graceMin): bool
    {
        return $this->attended() && $this->joinedAt !== null && $this->joinedAt->lessThanOrEqualTo($this->startsAt->addMinutes($graceMin));
    }
}
