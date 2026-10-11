<?php

namespace Modules\Games\Actions;

use Illuminate\Support\Facades\Validator;
use Modules\Games\Models\GameSettings;
use Modules\SharedKernel\Audit\AuditTrail;

/** The academy turns its learners' games on or off and sets the minutes a learner may play a day (null: no limit). */
final class SaveGameSettings
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(string $actorId, bool $enabled, ?int $dailyMinutes): GameSettings
    {
        Validator::make(['daily_minutes' => $dailyMinutes], ['daily_minutes' => ['nullable', 'integer', 'between:5,600']], [],
            ['daily_minutes' => __('games::games.settings.minutes')])->validate();
        $settings = GameSettings::current();
        $settings->fill(['enabled' => $enabled, 'daily_minutes' => $dailyMinutes, 'updated_by' => $actorId])->save();
        $this->audit->record('games.settings_saved', 'game_settings', $settings->academy_id, ['state' => $enabled ? 'on' : 'off', 'minutes' => $dailyMinutes]);

        return $settings;
    }
}
