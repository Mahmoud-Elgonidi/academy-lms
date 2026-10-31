<?php

namespace Modules\Evaluation\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Evaluation\Contracts\Events\LessonRated;
use Modules\Evaluation\Models\LessonRating;
use Modules\Evaluation\Queries\LessonFacts;
use Modules\People\Contracts\PeopleApi;

/**
 * The family's one-tap rating of a lesson (docs/architecture/01, "تقييم
 * الحصة بضغطة واحدة"): 1 to 5 stars, then if they like a few quick words
 * and a comment. The student or one of their guardians, for a lesson that
 * took place in the last two weeks; each person once, and they may change
 * it. It counts for whoever taught the lesson.
 */
final class RateLesson
{
    public const WINDOW_DAYS = 14;

    public function __construct(private readonly LessonFacts $lessons, private readonly PeopleApi $people) {}

    /** @param  list<string>  $tags */
    public function handle(string $sessionId, string $raterId, int $stars, array $tags = [], ?string $comment = null, string $via = 'app'): LessonRating
    {
        $l = 'evaluation::ratings.errors.';
        $lesson = $this->check($sessionId, $raterId);
        $comment = blank($comment) ? null : trim((string) $comment);
        if ($stars < 1 || $stars > 5) {
            throw ValidationException::withMessages(['stars' => __($l.'stars')]);
        }
        $tags = array_values(array_unique($tags));
        if (array_diff($tags, array_merge(...array_values(LessonRating::TAGS))) !== []) {
            throw ValidationException::withMessages(['tags' => __($l.'tags')]);
        }
        if ($comment !== null && mb_strlen($comment) > 500) {
            throw ValidationException::withMessages(['comment' => __($l.'comment')]);
        }

        return DB::transaction(function () use ($lesson, $raterId, $stars, $tags, $comment, $via) {
            $rating = LessonRating::query()->firstOrNew(['session_id' => $lesson->id, 'rater_id' => $raterId]);
            $previous = $rating->exists ? $rating->stars : null;
            $rating->fill([
                'student_id' => $lesson->student_id,
                'teacher_id' => $lesson->teacher_id,
                'stars' => $stars,
                'tags' => $tags,
                'comment' => $comment,
                'via' => $rating->exists ? $rating->via : $via,
            ])->save();
            DB::afterCommit(fn () => event(new LessonRated($lesson->academy_id, $rating->id, $lesson->id, $lesson->teacher_id, (string) $lesson->student_id, $stars, $previous)));

            return $rating;
        });
    }

    /** May this person rate this lesson now? The lesson, or why not. */
    public function check(string $sessionId, string $raterId): object
    {
        $l = 'evaluation::ratings.errors.';
        // The rater's own seat: themself, or the first child of theirs in it (a group lesson has a student per seat).
        $student = collect($this->lessons->students($sessionId))->first(fn (string $id) => $raterId === $id || in_array($raterId, $this->people->guardiansOf($id), true));
        $lesson = $student === null ? null : $this->lessons->find($sessionId, $student);
        if ($lesson === null || $lesson->student_id === null) {
            throw ValidationException::withMessages(['lesson' => __($l.'not_yours')]);
        }
        if ($lesson->status !== 'completed') {
            throw ValidationException::withMessages(['lesson' => __($l.'not_held')]);
        }
        if (CarbonImmutable::parse($lesson->ends_at)->lessThan(CarbonImmutable::now()->subDays(self::WINDOW_DAYS))) {
            throw ValidationException::withMessages(['lesson' => __($l.'too_old', ['days' => self::WINDOW_DAYS])]);
        }

        return $lesson;
    }
}
