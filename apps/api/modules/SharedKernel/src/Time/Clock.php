<?php

namespace Modules\SharedKernel\Time;

use Carbon\CarbonImmutable;

/**
 * The one way a clock time is written for people: 12 hours (5:00 م, 5:00 PM)
 * or 24 hours (17:00), the reader's choice (ViewerClock) unless a format is
 * given (a notification, written for each recipient). Patterns keep writing
 * `H:i` and pass through pattern(); times of the week ("17:00") go through
 * wall(), and an hour on a calendar's side through hour(). Data (API keys,
 * spreadsheets, validation formats) keeps 24 hours and never comes here.
 */
final class Clock
{
    /** The format to write in: the one given, else the reader's. */
    public static function format(?TimeFormat $format = null): TimeFormat
    {
        return $format ?? app(ViewerClock::class)->effective();
    }

    /** A Carbon pattern with its clock written the reader's way ("l j F · H:i" → "l j F · g:i A"). */
    public static function pattern(string $pattern, ?TimeFormat $format = null): string
    {
        if (self::format($format) === TimeFormat::TwentyFour) {
            return $pattern;
        }

        return (string) preg_replace('/H:i(:s)?/', 'g:i$1 A', $pattern);
    }

    /** A time of the week as kept ("17:00", "24:00" for the day's end) written the reader's way. */
    public static function wall(string $time, ?TimeFormat $format = null, ?string $locale = null): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})/', $time, $parts) !== 1) {
            return $time;
        }
        [, $hour, $minute] = $parts;
        if (self::format($format) === TimeFormat::TwentyFour) {
            return sprintf('%02d:%s', (int) $hour, $minute);
        }

        return CarbonImmutable::createFromTime((int) $hour % 24, (int) $minute)
            ->locale($locale ?? app()->getLocale())
            ->translatedFormat('g:i A');
    }

    /** An hour on a calendar's side: "5 م" / "5 PM", or "17:00". */
    public static function hour(int $hour, ?TimeFormat $format = null, ?string $locale = null): string
    {
        if (self::format($format) === TimeFormat::TwentyFour) {
            return sprintf('%02d:00', $hour);
        }

        return CarbonImmutable::createFromTime($hour % 24)->locale($locale ?? app()->getLocale())->translatedFormat('g A');
    }

    /** The same choice for a page's script (Intl.DateTimeFormat's hourCycle: h12 or h23). */
    public static function hourCycle(?TimeFormat $format = null): string
    {
        return self::format($format)->hourCycle();
    }
}
