<?php

namespace Modules\SharedKernel\Time;

/**
 * How a clock time is written: 12 hours with the day's half (5:00 م, 5:00 PM)
 * or 24 hours (17:00). The academy picks one for everyone, and each person
 * may pick their own (ViewerClock).
 */
enum TimeFormat: string
{
    case Twelve = 'h12';
    case TwentyFour = 'h24';

    /** What the academy starts with. */
    public const DEFAULT = self::Twelve;

    public static function of(mixed $value): ?self
    {
        return is_string($value) ? self::tryFrom($value) : null;
    }

    /** The Carbon pattern for the clock alone. */
    public function clock(): string
    {
        return $this === self::Twelve ? 'g:i A' : 'H:i';
    }

    /** The same for a browser's Intl.DateTimeFormat (hourCycle). */
    public function hourCycle(): string
    {
        return $this === self::Twelve ? 'h12' : 'h23';
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $format) => $format->value, self::cases());
    }
}
