<?php

namespace Modules\Learning\Contracts\Events;

/** Homework set for a student; Communication tells the student and the guardians. */
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
