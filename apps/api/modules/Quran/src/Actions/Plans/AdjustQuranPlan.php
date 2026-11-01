<?php

namespace Modules\Quran\Actions\Plans;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Quran\Domain\Programs\LessonPortion;
use Modules\Quran\Domain\Programs\MushafLayout;
use Modules\Quran\Domain\Programs\Position;
use Modules\Quran\Domain\Programs\ProgramRules;
use Modules\Quran\Models\QuranPlan;
use Modules\Quran\Programs\Mushaf;
use Modules\Quran\Support\QuranArea;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The teacher or the office changes a live plan: the learner's own pace
 * (the new amount in the program's unit, the near and far reviews; null
 * goes back to the program's) or where they are (a verse of the program's
 * scope, a lesson on the Noorani Qaida; "finished" once it is done).
 * Audited with the fields changed, never their values.
 */
final class AdjustQuranPlan
{
    public const FIELDS = ['new_amount', 'near_portions', 'far_pages', 'position'];

    public function __construct(
        private readonly Mushaf $mushaf,
        private readonly AuditTrail $audit,
        private readonly QuranArea $area,
    ) {}

    /** @param  array{new_amount?: ?float, near_portions?: ?int, far_pages?: ?int, position?: array{surah?: ?int, verse: int}}  $changes */
    public function handle(QuranPlan $plan, array $changes, ?string $actorId = null): QuranPlan
    {
        $this->area->require();
        if ($plan->status !== 'active') {
            throw ValidationException::withMessages(['plan' => __('quran::programs.errors.plan_closed')]);
        }
        $changes = array_intersect_key($changes, array_flip(self::FIELDS));
        $program = $plan->program;
        $pace = array_intersect_key($changes, array_flip(['new_amount', 'near_portions', 'far_pages']));
        $errors = ProgramRules::paceErrors($program->track, $program->unit, array_filter($pace, fn ($v) => $v !== null));
        if ($errors !== []) {
            throw ValidationException::withMessages(array_map(fn (string $why) => __('quran::programs.errors.'.$why), $errors));
        }
        foreach ($pace as $field => $value) {
            $plan->setAttribute($field, $value === null ? null : ($field === 'new_amount' ? (float) $value : (int) $value));
        }
        if (array_key_exists('position', $changes)) {
            $at = $this->position($plan, $changes['position']);
            $plan->fill(QuranPlan::positionColumns($at));
            $plan->finished_at = $at === null ? CarbonImmutable::now() : null;
        }
        $changed = array_values(array_intersect(array_keys($plan->getDirty()), ['new_amount', 'near_portions', 'far_pages', 'position_surah', 'position_verse']));
        if ($changed !== []) {
            $plan->save();
            $this->audit->record('quran.plan_adjusted', 'quran_plans', $plan->id, [
                'quran_fields' => array_values(array_unique(array_map(fn (string $f) => str_starts_with($f, 'position_') ? 'position' : $f, $changed))),
            ]);
        }

        return $plan;
    }

    /** @param  mixed  $given  {surah, verse}, {verse} on the Noorani Qaida, or null: finished */
    private function position(QuranPlan $plan, mixed $given): ?Position
    {
        if ($given === null) {
            return null;
        }
        $shape = $plan->shape();
        $layout = $this->mushaf->layout();
        $at = ! is_array($given) || ! isset($given['verse']) ? null
            : ($shape->isNoorani() ? Position::lesson((int) $given['verse']) : Position::verse((int) ($given['surah'] ?? 0), (int) $given['verse']));
        if ($at === null || (! $shape->isNoorani() && $layout === null) || ! LessonPortion::holds($shape, $layout ?? new MushafLayout([1 => 1], [1], [1]), $at)) {
            throw ValidationException::withMessages(['position' => __('quran::programs.errors.start')]);
        }

        return $at;
    }
}
