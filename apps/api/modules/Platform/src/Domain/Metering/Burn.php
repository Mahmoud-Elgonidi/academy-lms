<?php

namespace Modules\Platform\Domain\Metering;

/**
 * How fast a meter is used: the average of the last DAYS days, and at that
 * pace in about how many days what is left runs out (none while nothing is
 * used).
 */
final class Burn
{
    public const DAYS = 14;

    /** @param  list<int>  $daily  the use of each of the last days (missing days count as none) */
    public static function perDay(array $daily): float
    {
        return array_sum(array_map(fn (int $n) => max(0, $n), $daily)) / self::DAYS;
    }

    /** @param  list<int>  $daily */
    public static function daysLeft(int $left, array $daily): ?int
    {
        $pace = self::perDay($daily);
        if ($pace <= 0) {
            return null;
        }

        return max(0, (int) floor(max(0, $left) / $pace));
    }
}
