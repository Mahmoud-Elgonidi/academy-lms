<?php

namespace Modules\Crm\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Contracts\Events\TrialAssessed;
use Modules\Crm\Enums\LeadStage;
use Modules\Crm\Models\LeadActivity;
use Modules\Crm\Models\LeadTrial;

/**
 * The teacher's word after a trial (docs/architecture/01, section 14,
 * "تقييم التجربة"): the level they found, the plan and lessons a week they
 * suggest, and notes for the academy. Only the trial's own teacher, once
 * the lesson took place (a manager may write it for them); they may correct
 * it later. Sales hear of it.
 */
final class AssessTrial
{
    /** @param  array{level?: ?string, plan_id?: ?string, per_week?: int|string|null, notes?: ?string}  $data */
    public function handle(LeadTrial $trial, array $data, string $actorId, bool $asManager = false): LeadTrial
    {
        if (! $asManager && $trial->teacher_id !== $actorId) {
            throw ValidationException::withMessages(['trial' => __('crm::trials.errors.not_yours')]);
        }
        if ($trial->status !== 'attended') {
            throw ValidationException::withMessages(['trial' => __('crm::trials.errors.not_attended')]);
        }
        $valid = Validator::make($data, [
            'level' => ['required', 'string', 'max:120'],
            'plan_id' => ['nullable', Rule::exists('plans', 'id')],
            'per_week' => ['nullable', 'integer', 'between:1,7'],
            'notes' => ['required', 'string', 'max:2000'],
        ], [], __('crm::trials.fields'))->validate();

        return DB::transaction(function () use ($trial, $valid, $actorId) {
            $first = ! $trial->isAssessed();
            $trial->forceFill([
                'level' => trim($valid['level']),
                'recommended_plan_id' => $valid['plan_id'] ?? null,
                'recommended_per_week' => isset($valid['per_week']) ? (int) $valid['per_week'] : null,
                'assessment' => trim($valid['notes']),
                'assessed_by' => $actorId,
                'assessed_at' => CarbonImmutable::now(),
            ])->save();
            $lead = $trial->lead;
            LeadActivity::record($lead, 'assessed', $trial->assessment, ['level' => $trial->level, 'again' => ! $first], $actorId);
            if ($lead->stage === LeadStage::Attended) {
                $lead->moveTo(LeadStage::Assessed, $actorId, log: false);
            }
            $academyId = $lead->academy_id;
            if ($first) {
                DB::afterCommit(fn () => event(new TrialAssessed($academyId, $trial->id, $lead->id, $actorId)));
            }

            return $trial;
        });
    }
}
