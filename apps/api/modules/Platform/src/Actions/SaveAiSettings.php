<?php

namespace Modules\Platform\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Platform\Models\AiSettings;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Plans\AcademyPlan;
use Modules\SharedKernel\Plans\Feature;

/**
 * Which AI helpers the academy uses (Settings › AI). Needs the plan's AI
 * area; audited with the helpers that are on, nothing else.
 */
final class SaveAiSettings
{
    public function __construct(
        private readonly AuditTrail $audit,
        private readonly AcademyPlan $plan,
    ) {}

    /** @param  array<string, bool>  $on  by AiFeature value; others are ignored */
    public function handle(array $on, string $actorId): AiSettings
    {
        $this->plan->requireFeature(Feature::Ai);

        return DB::transaction(function () use ($on, $actorId) {
            $settings = AiSettings::current();
            foreach (AiFeature::cases() as $feature) {
                $settings->setAttribute($feature->value, (bool) ($on[$feature->value] ?? false));
            }
            $settings->updated_by = $actorId;
            $settings->save();

            $this->audit->record('academy.ai_settings_saved', 'ai_settings', $settings->academy_id, [
                'helpers' => array_values(array_map(fn (AiFeature $f) => $f->value, array_filter(AiFeature::cases(), fn (AiFeature $f) => $settings->isOn($f)))),
            ]);

            return $settings;
        });
    }
}
