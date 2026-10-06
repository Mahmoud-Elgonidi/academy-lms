<?php

namespace Modules\Learning\Contracts\Events;

/** The student handed homework in; the teacher is told. */
final readonly class HomeworkSubmitted
{
    public function __construct(
        public string $academyId,
        public string $homeworkId,
        public string $studentId,
        public string $teacherId,
    ) {}
}
