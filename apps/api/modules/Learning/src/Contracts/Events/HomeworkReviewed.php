<?php

namespace Modules\Learning\Contracts\Events;

/** The teacher marked homework; the student and the guardians see the grade and the feedback. */
final readonly class HomeworkReviewed
{
    public function __construct(
        public string $academyId,
        public string $homeworkId,
        public string $studentId,
        public ?int $grade,
    ) {}
}
