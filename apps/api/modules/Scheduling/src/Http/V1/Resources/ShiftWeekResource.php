<?php

namespace Modules\Scheduling\Http\V1\Resources;

use Modules\Scheduling\Domain\Shifts\WeeklyShifts;

/** A week of blocks as the apps draw it: on the teacher's clock, with weekly hours. */
final class ShiftWeekResource
{
    /** @return array{timezone: string, weekly_minutes: int, blocks: list<array<string, mixed>>} */
    public static function make(WeeklyShifts $week): array
    {
        return ['timezone' => $week->timezone, 'weekly_minutes' => $week->weeklyMinutes(), 'blocks' => $week->toArray()];
    }
}
