<?php

namespace Modules\Learning\Contracts;

use Carbon\CarbonImmutable;
use Modules\Learning\Contracts\DTOs\HomeworkLine;
use Modules\Learning\Contracts\DTOs\PlanLine;
use Modules\Learning\Contracts\DTOs\PracticeFocus;
use Modules\Learning\Contracts\DTOs\PracticeRecorded;
use Modules\Learning\Contracts\DTOs\PracticeRound;

/** What other modules may ask of Learning. */
interface LearningApi
{
    /**
     * Today's plan for a lesson of this enrollment, with each lesson's materials (signed links; the teachers-only ones
     * only for a teacher); empty when it follows no program.
     *
     * @return list<PlanLine>
     */
    public function planFor(?string $enrollmentId, CarbonImmutable $lessonStart, bool $forTeacher = true): array;

    /** The last homework the student was set before this lesson: in the course when there is one, else any of theirs. */
    public function lastHomework(string $studentId, ?string $enrollmentId, CarbonImmutable $lessonStart): ?HomeworkLine;

    /**
     * What the learner should practise now: homework asking for practice, the last lesson, reviews due, where each course stands.
     *
     * @return list<PracticeFocus>
     */
    public function practiceFocus(string $studentId, ?CarbonImmutable $now = null): array;

    /** A practice round finished in a game: kept, counted per lesson, and handed in for homework that asks for it. */
    public function recordPractice(PracticeRound $round): PracticeRecorded;
}
