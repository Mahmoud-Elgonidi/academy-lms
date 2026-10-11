<?php

namespace Modules\Learning\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Learning\Contracts\DTOs\PracticeFocus;
use Modules\Learning\Contracts\DTOs\PracticeRecorded;
use Modules\Learning\Contracts\DTOs\PracticeRound;
use Modules\Learning\Contracts\Events\HomeworkSubmitted;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\PracticeRoundEntry;

/**
 * A learner finished a practice round in a game (docs/architecture/02,
 * section 18): the round is kept, each lesson's practice adds up for the
 * teacher and the roadmap, and homework that asked for this practice keeps
 * its best round; homework that is practice only is handed in by it (the
 * teacher hears, as with any hand-in). Homework already marked keeps its
 * mark.
 */
final class RecordPractice
{
    public function handle(PracticeRound $round): PracticeRecorded
    {
        Validator::make([
            'focus' => $round->focus, 'total' => $round->total, 'correct' => $round->correct, 'stars' => $round->stars, 'seconds' => $round->seconds,
            'nodes' => array_keys($round->byNode),
        ], [
            'focus' => ['required', 'in:'.implode(',', [...PracticeFocus::REASONS, 'free'])],
            'total' => ['required', 'integer', 'between:1,100'],
            'correct' => ['required', 'integer', 'min:0', 'lte:total'],
            'stars' => ['required', 'integer', 'between:0,3'],
            'seconds' => ['required', 'integer', 'between:0,86400'],
            'nodes.*' => ['uuid'],
        ])->validate();

        return DB::transaction(function () use ($round) {
            // A homework the teacher took back (or that no longer asks for practice) while the round was played:
            // the round still counts as practice, for no homework.
            $homework = $round->homeworkId === null ? null
                : Homework::query()->where('student_id', $round->studentId)->lockForUpdate()->find($round->homeworkId);
            $homework = $homework?->hasPractice() ? $homework : null;

            $entry = PracticeRoundEntry::query()->create([
                'student_id' => $round->studentId,
                'enrollment_id' => $round->enrollmentId ?? $homework?->enrollment_id,
                'homework_id' => $homework?->id,
                'game' => mb_substr($round->game, 0, 41),
                'focus' => $round->focus,
                'correct' => $round->correct,
                'total' => $round->total,
                'stars' => $round->stars,
                'seconds' => $round->seconds,
                'played_by' => $round->playedBy,
                'played_at' => $round->playedAt,
            ]);

            $enrollmentId = $entry->enrollment_id;
            if ($enrollmentId !== null) {
                foreach ($round->byNode as $nodeId => $counts) {
                    $answered = max(0, (int) ($counts['answered'] ?? 0));
                    $correct = min($answered, max(0, (int) ($counts['correct'] ?? 0)));
                    DB::statement(
                        'insert into practice_stats (academy_id, enrollment_id, node_id, student_id, rounds, answered, correct, last_at)
                         values (app_current_tenant(), ?, ?, ?, 1, ?, ?, ?)
                         on conflict (academy_id, enrollment_id, node_id) do update set
                            rounds = practice_stats.rounds + 1, answered = practice_stats.answered + excluded.answered,
                            correct = practice_stats.correct + excluded.correct, last_at = greatest(practice_stats.last_at, excluded.last_at)',
                        [$enrollmentId, $nodeId, $round->studentId, $answered, $correct, $round->playedAt->utc()],
                    );
                }
            }

            $handedIn = false;
            if ($homework !== null) {
                $result = ['correct' => $round->correct, 'total' => $round->total, 'stars' => $round->stars, 'game' => $round->gameTitle, 'played_at' => $round->playedAt->utc()->toIso8601ZuluString()];
                if (self::better($result, $homework->practice_result)) {
                    $homework->practice_result = $result;
                }
                if ($homework->accepts === 'practice' && in_array($homework->status, ['assigned', 'missed'], true)) {
                    $homework->submissions()->create([
                        'body' => __('learning::homework.practice_body', ['game' => $round->gameTitle, 'correct' => $round->correct, 'total' => $round->total, 'stars' => $round->stars]),
                        'submitted_by' => $round->playedBy,
                        'submitted_at' => CarbonImmutable::now(),
                    ]);
                    $homework->status = 'submitted';
                    $handedIn = true;
                    DB::afterCommit(fn () => event(new HomeworkSubmitted($homework->academy_id, $homework->id, $homework->student_id, $homework->teacher_id)));
                }
                $homework->save();
            }

            return new PracticeRecorded($entry->id, $handedIn, $homework?->title);
        });
    }

    /**
     * @param  array{correct: int, total: int, stars: int}  $new
     * @param  ?array<string, mixed>  $old
     */
    private static function better(array $new, ?array $old): bool
    {
        if ($old === null) {
            return true;
        }

        return [$new['stars'], $new['correct'] / max(1, $new['total'])] > [(int) ($old['stars'] ?? 0), (int) ($old['correct'] ?? 0) / max(1, (int) ($old['total'] ?? 1))];
    }
}
