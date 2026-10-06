<?php

namespace Modules\Evaluation\Contracts\Events;

/** A family rated a lesson (or changed their rating); a low one reaches the supervisors. */
final readonly class LessonRated
{
    public function __construct(
        public string $academyId,
        public string $ratingId,
        public string $sessionId,
        public string $teacherId,
        public string $studentId,
        public int $stars,
        public ?int $previousStars,
    ) {}
}
