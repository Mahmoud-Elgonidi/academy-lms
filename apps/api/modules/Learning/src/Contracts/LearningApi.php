<?php

namespace Modules\Learning\Contracts;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Learning\Contracts\DTOs\HomeworkLine;
use Modules\Learning\Contracts\DTOs\JourneyStation;
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

    /**
     * The lessons on the learner's journey they may practise now (what they learnt, where they are, their last
     * report's lessons and homework asking for practice), each with the island its level is drawn as.
     *
     * @return list<JourneyStation>
     */
    public function journeyStations(string $studentId): array;

    /** The learner's stars on their journey: lessons, practice, homework, lessons mastered and the week's chests. */
    public function stars(string $studentId): int;

    /** A practice round finished in a game: kept, counted per lesson, and handed in for homework that asks for it. */
    public function recordPractice(PracticeRound $round): PracticeRecorded;

    /**
     * A guardian cheers their child on (a few a day; the child sees it and hears it by push).
     *
     * @param  string  $kind  clap, star, heart or fire
     *
     * @throws ValidationException not their child, or too many today
     */
    public function cheer(string $fromId, string $studentId, string $kind = 'heart'): void;
}
