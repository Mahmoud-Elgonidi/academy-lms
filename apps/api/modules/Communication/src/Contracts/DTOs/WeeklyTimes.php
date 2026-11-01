<?php

namespace Modules\Communication\Contracts\DTOs;

use Carbon\CarbonImmutable;

/**
 * A course's weekly times ("Tuesday 6:00 PM and Saturday 6:00 PM"), each
 * given as its next lesson's instant and written on each recipient's own
 * clock (their time zone), in their language and their way of reading a
 * clock, in week order as they read it.
 */
final readonly class WeeklyTimes
{
    /** @param  list<CarbonImmutable>  $starts  one instant per weekly time */
    public function __construct(public array $starts) {}
}
