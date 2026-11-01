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
 * "تقييم التجربة"; 02, section 27, J2): what the family reads in its report
 * (the level they found, strengths, what to work on, their words to the
 * family, the plan and lessons a week they suggest) and notes for the
 * academy alone. Only the trial's own teacher, once the lesson took place (a
 * manager may write it for them); they may correct it later, and the report
 * reads what it says now (no second message). The first assessment makes
 * the academy's offer, when it has one (IssueTrialOffer); the family's report
 * and sales' notice go out after the commit (TrialAssessed).
 */
final class AssessTrial
{
    public const FAMILY_TEXT_MAX = 1000;

    public function __construct(private readonly IssueTrialOffer $offer) {}

    /** @param  array{level?: ?string, plan_id?: ?string, per_week?: int|string|null, notes?: ?string, strengths?: ?string, to_work_on?: ?string, family_note?: ?string}  $data */
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
            'strengths' => ['nullable', 'string', 'max:'.self::FAMILY_TEXT_MAX],
            'to_work_on' => ['nullable', 'string', 'max:'.self::FAMILY_TEXT_MAX],
            'family_note' => ['nullable', 'string', 'max:'.self::FAMILY_TEXT_MAX],
        ], [], __('crm::trials.fields'))->validate();
        $text = fn (string $field) => filled($valid[$field] ?? null) ? trim((string) $valid[$field]) : null;

        return DB::transaction(function () use ($trial, $valid, $actorId, $text) {
            $first = ! $trial->isAssessed();
            // A family field left out keeps what it said (an app from before them corrects the rest without blanking them).
            $family = [];
            foreach (['strengths', 'to_work_on', 'family_note'] as $field) {
                if (array_key_exists($field, $valid)) {
                    $family[$field] = $text($field);
                }
            }
            $trial->forceFill([
                'level' => trim($valid['level']),
                'recommended_plan_id' => $valid['plan_id'] ?? null,
                'recommended_per_week' => isset($valid['per_week']) ? (int) $valid['per_week'] : null,
                'assessment' => trim($valid['notes']),
                'assessed_by' => $actorId,
                'assessed_at' => CarbonImmutable::now(),
            ] + $family)->save();
            $lead = $trial->lead;
            LeadActivity::record($lead, 'assessed', $trial->assessment, ['level' => $trial->level, 'again' => ! $first], $actorId);
            if ($lead->stage === LeadStage::Attended) {
                $lead->moveTo(LeadStage::Assessed, $actorId, log: false);
            }
            $academyId = $lead->academy_id;
            if ($first) {
                $this->offer->handle($trial);
                DB::afterCommit(fn () => event(new TrialAssessed($academyId, $trial->id, $lead->id, $actorId)));
            }

            return $trial;
        });
    }
}
