<?php

namespace Modules\Learning\Mushaf;

use Modules\Scheduling\Contracts\DTOs\LessonCard;

/** A Quran lesson with a shared mushaf, and who is looking at it: its teacher, its student, or one of the student's guardians. */
final readonly class MushafLesson
{
    public function __construct(
        public LessonCard $card,
        public string $viewerId,
        public string $role,            // teacher | student | guardian
    ) {}

    public function studentId(): string
    {
        return (string) $this->card->studentId;
    }

    public function teaches(): bool
    {
        return $this->role === 'teacher';
    }
}
