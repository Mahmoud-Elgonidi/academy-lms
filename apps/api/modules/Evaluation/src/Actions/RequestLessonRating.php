<?php

namespace Modules\Evaluation\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Evaluation\Contracts\Events\LessonRatingDue;
use Modules\Evaluation\Models\LessonRating;
use Modules\Evaluation\Queries\LessonFacts;

/**
 * After a lesson took place, the family is asked how it went, with their
 * own link to rate it in one tap: not after every lesson, but the first
 * one of each week for each student (the message carries the week in its
 * dedupe key), and not when someone in the family rated this week.
 */
final class RequestLessonRating
{
    public function __construct(private readonly LessonFacts $lessons) {}

    public function handle(string $sessionId, string $outcome): bool
    {
        $lesson = $outcome === 'completed' ? $this->lessons->find($sessionId) : null;
        if ($lesson === null || $lesson->student_id === null || $lesson->status !== 'completed') {
            return false;
        }
        if (LessonRating::query()->where('student_id', $lesson->student_id)->where('created_at', '>=', CarbonImmutable::now()->startOfWeek())->exists()) {
            return false;
        }
        DB::afterCommit(fn () => event(new LessonRatingDue($lesson->academy_id, $lesson->id, $lesson->student_id)));

        return true;
    }
}
