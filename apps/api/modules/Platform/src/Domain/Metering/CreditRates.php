<?php

namespace Modules\Platform\Domain\Metering;

/**
 * What a helper's request costs in AI credits: its base for each request,
 * and for one that hears a recording, its rate for each minute of audio
 * (rounded up, at least one). The estimate held before a recording is heard
 * comes from its size, at a modest bitrate and never more than
 * MAX_ESTIMATE_MINUTES; the actual is settled from the seconds heard.
 */
final class CreditRates
{
    /** About 64 kbit/s, the voice notes and recitations the apps record. */
    public const AUDIO_BYTES_PER_SECOND = 8000;

    public const MAX_ESTIMATE_MINUTES = 30;

    public static function charge(int $base, int $perMinute, ?float $seconds = null): int
    {
        $minutes = $seconds === null ? 0 : self::minutes($seconds);

        return max(0, $base) + max(0, $perMinute) * $minutes;
    }

    /** Minutes of audio as charged: rounded up, at least one for any recording. */
    public static function minutes(float $seconds): int
    {
        return max(1, (int) ceil(max(0.0, $seconds) / 60));
    }

    /** The minutes a recording of this size is taken to hold before it is heard. */
    public static function estimateMinutes(int $bytes): int
    {
        return min(self::MAX_ESTIMATE_MINUTES, self::minutes($bytes / self::AUDIO_BYTES_PER_SECOND));
    }

    /** The least a request of this rate can cost (one minute of audio for a helper that hears). */
    public static function least(int $base, int $perMinute): int
    {
        return max(1, $base + ($perMinute > 0 ? $perMinute : 0));
    }
}
