<?php

namespace Modules\Learning\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Contracts\DTOs\PracticeItem;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\LearningSettings;
use Modules\Learning\Models\SessionReport;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\SharedKernel\Games\GameCatalog;
use Modules\SharedKernel\Tenancy\TenantContext;

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
        private readonly CurriculumApi $curriculum,
        private readonly PeopleApi $people,
    ) {}

    /**
     * @throws AuthorizationException not the report's teacher
     * @throws ValidationException no lessons in the report, an unknown game or one the learner cannot play, or homework already handed in
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
        // Only a game the learner can play: made for their look, with enough of these lessons' items of the kinds it plays.
        $items = array_merge(...array_values($this->curriculum->practiceItems($lessons)));
        $playable = count(array_filter($items, fn (PracticeItem $i) => $game->plays($i->kind)));
        if (! $game->fits($this->people->studentProfile($report->student_id)->stage->value) || $playable < $game->minItems) {
            throw ValidationException::withMessages(['game' => __('learning::homework.game.errors.no_fit')]);
        }
        $existing = $report->homework()->first();
        if ($existing !== null && ($existing->status !== 'assigned' || $existing->submissions()->exists())) {
            throw ValidationException::withMessages(['game' => __('learning::homework.errors.closed')]);
        }

        $locale = app(TenantContext::class)->current()?->defaultLocale ?? app()->getLocale();
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
            // Read by the learner, so in the academy's language rather than the teacher's screen's.
            'title' => __('learning::homework.game.title', ['game' => $game->title($locale)], $locale),
        ]);
    }
}
