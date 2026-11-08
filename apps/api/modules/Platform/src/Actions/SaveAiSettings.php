<?php

namespace Modules\Platform\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Platform\Models\AiSettings;
use Modules\SharedKernel\Ai\Capabilities;
use Modules\SharedKernel\Ai\Capability;
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
        private readonly Capabilities $capabilities,
    ) {}

    /** @param  array<string, bool>  $on  by capability key; unknown keys are ignored, a missing one is off for the first five and kept for the rest */
    public function handle(array $on, string $actorId): AiSettings
    {
        $this->plan->requireFeature(Feature::Ai);

        return DB::transaction(function () use ($on, $actorId) {
            $settings = AiSettings::current();
            foreach ($this->capabilities->all() as $key => $capability) {
                if ($capability->feature() !== null || array_key_exists($key, $on)) {
                    $settings->setOn($capability, (bool) ($on[$key] ?? false));
                }
            }
            $settings->updated_by = $actorId;
            $settings->save();

            $this->audit->record('academy.ai_settings_saved', 'ai_settings', $settings->academy_id, [
                'helpers' => array_values(array_keys(array_filter($this->capabilities->all(), fn (Capability $c) => $settings->isOn($c)))),
            ]);

            return $settings;
        });
    }
}
