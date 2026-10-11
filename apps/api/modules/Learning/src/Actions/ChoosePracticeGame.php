<?php

namespace Modules\Learning\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\LearningSettings;
use Modules\Learning\Models\SessionReport;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\SharedKernel\Games\GameCatalog;

/**
 * A teacher makes a lesson's homework a game (docs/architecture/02, section
 * 18; the approved design, Teacher): the practice of the lessons in their
 * report, played in the game they chose (its catalog key; the learner's quest
 * offers that game first), due before the next lesson or after the
 * academy's usual days, and practice only (handed in by playing) or with
 * whatever else the homework asks. The report's homework is changed while
 * nothing was handed in; else a new one is set.
 */
final class ChoosePracticeGame
{
    public function __construct(
        private readonly AssignHomework $assign,
        private readonly GameCatalog $catalog,
        private readonly AttendanceApi $attendance,
    ) {}

    /**
     * @throws AuthorizationException not the report's teacher
     * @throws ValidationException no lessons in the report, an unknown game, or homework already handed in
     */
    public function handle(SessionReport $report, string $teacherId, string $gameKey, bool $practiceOnly, bool $beforeNext, ?CarbonImmutable $now = null): Homework
    {
        $now ??= CarbonImmutable::now();
        if ($report->teacher_id !== $teacherId) {
            throw new AuthorizationException;
        }
        $lessons = $report->items()->pluck('node_id')->all();
        if ($report->kind !== 'lesson' || $report->submitted_at === null || $lessons === []) {
            throw ValidationException::withMessages(['game' => __('learning::homework.game.errors.report')]);
        }
        $game = $this->catalog->game($gameKey);
        if ($game === null) {
            throw ValidationException::withMessages(['game' => __('learning::homework.game.errors.game')]);
        }
        $existing = $report->homework()->first();
        if ($existing !== null && ($existing->status !== 'assigned' || $existing->submissions()->exists())) {
            throw ValidationException::withMessages(['game' => __('learning::homework.errors.closed')]);
        }

        $usual = $report->submitted_at->addDays(LearningSettings::current()->homework_days);
        $next = $beforeNext ? collect($this->attendance->lessonsOfStudent($report->student_id, $now, $now->addDays(30)))
            ->first(fn ($l) => $l->startsAt->greaterThan($now) && $l->enrollmentId === $report->enrollment_id && $l->status === 'scheduled')?->startsAt : null;
        $fields = [
            'practice_nodes' => array_values($lessons),
            'practice_game' => $game->key,
            'due_at' => $next ?? $existing?->due_at ?? $usual,
            'accepts' => $practiceOnly ? 'practice' : (($existing->accepts ?? 'any') === 'practice' ? 'any' : ($existing->accepts ?? 'any')),
        ];

        if ($existing !== null) {
            $existing->fill($fields)->save();

            return $existing;
        }

        return $this->assign->handle($fields + [
            'teacher_id' => $report->teacher_id,
            'student_id' => $report->student_id,
            'enrollment_id' => $report->enrollment_id,
            'session_id' => $report->session_id,
            'report_id' => $report->id,
            'title' => __('learning::homework.game.title', ['game' => $game->title()]),
        ]);
    }
}
