<?php

namespace Modules\Platform\Domain\Metering;

use Carbon\CarbonImmutable;

/**
 * The minutes a lesson's room on our own video counts (docs/architecture/02,
 * section 11): its actual open time, rounded up to the minute, and never more
 * than the lesson's length and MARGIN_MINUTES (a room left open costs the
 * academy no more than that). An outside link (Zoom, Meet) counts nothing.
 */
final class VideoCharge
{
    public const MARGIN_MINUTES = 15;

    public static function minutes(CarbonImmutable $startedAt, CarbonImmutable $finishedAt, int $lessonMinutes): int
    {
        $seconds = $finishedAt->getTimestamp() - $startedAt->getTimestamp();
        if ($seconds <= 0) {
            return 0;
        }

        return min((int) ceil($seconds / 60), self::cap($lessonMinutes));
    }

    public static function cap(int $lessonMinutes): int
    {
        return max(0, $lessonMinutes) + self::MARGIN_MINUTES;
    }

    /** What is still to be counted for a room already counted for $counted minutes (a room opened again). */
    public static function delta(int $total, int $counted): int
    {
        return max(0, $total - $counted);
    }
}
