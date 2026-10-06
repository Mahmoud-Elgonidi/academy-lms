<?php

namespace Modules\Scheduling\Actions\Holidays;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Domain\Models\AcademyHoliday;

/**
 * Takes back a holiday that has not begun: lessons are generated on those
 * days again. The ones it cancelled stay cancelled (their minutes are back
 * already); the academy books them again if it wants them.
 */
final class RemoveHoliday
{
    public function handle(AcademyHoliday $holiday): void
    {
        if ($holiday->starts_at->lessThanOrEqualTo(CarbonImmutable::now())) {
            throw ValidationException::withMessages(['holiday' => __('scheduling::timeoff.holidays.errors.started')]);
        }

        $holiday->delete();
    }
}
