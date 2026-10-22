<?php

namespace Modules\Communication\Contracts\DTOs;

use Carbon\CarbonImmutable;

/**
 * A weekday and a time already on the clock it belongs to ("Tuesday 6:00 PM"
 * for a weekly lesson on the student's clock), written in each recipient's
 * language and their way of reading a clock (12 or 24 hours); its zone is
 * kept, not the recipient's.
 */
final readonly class WeekdayTime
{
    public function __construct(public CarbonImmutable $local) {}
}
