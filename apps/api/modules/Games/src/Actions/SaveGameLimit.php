<?php

namespace Modules\Games\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Games\Models\GameLimit;

/**
 * A guardian sets their child's play: on or off, minutes a day (never more
 * than the academy allows; null: the academy's), and no play at night
 * (null keeps what was set). The caller has
 * checked the guardian follows the child (FamilyView).
 */
final class SaveGameLimit
{
    public const MINUTES = [10, 15, 20, 30, 45, 60, 90, 120];

    public function handle(string $studentId, string $guardianId, bool $enabled, ?int $dailyMinutes, ?bool $nightLock = null): GameLimit
    {
        Validator::make(['daily_minutes' => $dailyMinutes], ['daily_minutes' => ['nullable', 'integer', Rule::in(self::MINUTES)]], [],
            ['daily_minutes' => __('games::games.limits.minutes')])->validate();

        return GameLimit::query()->updateOrCreate(['student_id' => $studentId], array_filter([
            'enabled' => $enabled, 'daily_minutes' => $dailyMinutes, 'updated_by' => $guardianId, 'night_lock' => $nightLock,
        ], fn ($v, $k) => $k !== 'night_lock' || $v !== null, ARRAY_FILTER_USE_BOTH));
    }
}
