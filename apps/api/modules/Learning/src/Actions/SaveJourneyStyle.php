<?php

namespace Modules\Learning\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Learning\Models\LearningSettings;
use Modules\Learning\Queries\Journey\JourneyMap;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The academy chooses how its students see their journey: playful (each in
 * their stage's look, with islands and games), calm (levels and missions, no
 * islands) or plain (no stars either).
 */
final class SaveJourneyStyle
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(string $style): LearningSettings
    {
        Validator::make(['journey_style' => $style], ['journey_style' => ['required', Rule::in(JourneyMap::STYLES)]])->validate();
        $settings = LearningSettings::current();
        $settings->fill(['journey_style' => $style])->save();
        $this->audit->record('games.journey_style_saved', 'learning_settings', $settings->academy_id, ['style' => $style]);

        return $settings;
    }
}
