<?php

namespace Modules\Games\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Games\Models\GameLimit;

/**
 * A guardian sets their child's play: on or off, and minutes a day (never
 * more than the academy allows; null: the academy's). The caller has
 * checked the guardian follows the child (FamilyView).
 */
final class SaveGameLimit
{
    public const MINUTES = [10, 15, 20, 30, 45, 60, 90, 120];

    public function handle(string $studentId, string $guardianId, bool $enabled, ?int $dailyMinutes): GameLimit
    {
        Validator::make(['daily_minutes' => $dailyMinutes], ['daily_minutes' => ['nullable', 'integer', Rule::in(self::MINUTES)]], [],
            ['daily_minutes' => __('games::games.limits.minutes')])->validate();

        return GameLimit::query()->updateOrCreate(['student_id' => $studentId], ['enabled' => $enabled, 'daily_minutes' => $dailyMinutes, 'updated_by' => $guardianId]);
    }
}
