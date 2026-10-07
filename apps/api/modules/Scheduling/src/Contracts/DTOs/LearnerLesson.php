<?php

namespace Modules\Scheduling\Contracts\DTOs;

use Carbon\CarbonImmutable;

/** One of a student's lessons as their challenges count it: its outcome and when the student first came in. */
final readonly class LearnerLesson
{
    public function __construct(
        public string $id,
        /** scheduled, in_progress, completed, student_absent, teacher_absent, needs_review, cancelled */
        public string $status,
        public CarbonImmutable $startsAt,
        public ?CarbonImmutable $joinedAt,
        public ?string $enrollmentId,
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
