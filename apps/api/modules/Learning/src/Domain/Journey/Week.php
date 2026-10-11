<?php

namespace Modules\Learning\Domain\Journey;

use Carbon\CarbonImmutable;

/**
 * The journey's week (docs/architecture/02, section 18): Saturday to Friday
 * on the learner's own clock, for the week's chest (and the old weekly
 * challenges). A week the clocks change in is as long as the clock says.
 */
final class Week
{
    /** Weeks start on Saturday, on the learner's own clock. */
    public const STARTS = CarbonImmutable::SATURDAY;

    /** The start of the week $at falls in: midnight of its Saturday on this clock. */
    public static function start(CarbonImmutable $at, string $zone): CarbonImmutable
    {
        return $at->setTimezone($zone)->startOfWeek(self::STARTS)->startOfDay();
    }
}
