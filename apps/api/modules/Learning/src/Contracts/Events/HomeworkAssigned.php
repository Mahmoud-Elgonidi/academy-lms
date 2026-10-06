<?php

namespace Modules\Learning\Contracts\Events;

/** Homework set for a student; Learning tells the student and the guardians through Communication. */
final readonly class HomeworkAssigned
{
    public function __construct(
        public string $academyId,
        public string $homeworkId,
        public string $studentId,
        public string $teacherId,
        public string $dueAtUtc,
    ) {}
}
