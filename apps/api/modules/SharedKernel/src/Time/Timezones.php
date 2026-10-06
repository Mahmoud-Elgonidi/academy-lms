<?php

namespace Modules\SharedKernel\Time;

use DateTimeImmutable;
use DateTimeZone;

/** IANA timezone names only ("Africa/Cairo"), never offsets ("+03:00"). */
final class Timezones
{
    /** @var array<string, true>|null */
    private static ?array $known = null;

    /** @return list<string> every IANA name PHP knows */
    public static function all(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    /** The city of an IANA zone in the current language (or the one given): "Africa/Cairo" → "القاهرة". */
    public static function city(string $timezone, ?string $locale = null): string
    {
        $key = 'shared::cities.'.$timezone;
        $name = __($key, [], $locale);

        return $name === $key ? str_replace('_', ' ', substr(strrchr($timezone, '/') ?: $timezone, 1) ?: $timezone) : $name;
    }

    public static function isValid(mixed $timezone): bool
    {
        if (! is_string($timezone) || $timezone === '') {
            return false;
        }

        self::$known ??= array_fill_keys(DateTimeZone::listIdentifiers(), true);

        return isset(self::$known[$timezone]);
    }

    /**
     * Whether two zones keep the same clock over the next year: Asia/Riyadh and
     * Asia/Kuwait do; Africa/Cairo and Asia/Riyadh only agree outside Egypt's summer time.
     */
    public static function sameClock(string $a, string $b, ?DateTimeImmutable $from = null): bool
    {
        if ($a === $b) {
            return true;
        }

        $from ??= new DateTimeImmutable('now');
        $za = new DateTimeZone($a);
        $zb = new DateTimeZone($b);

        for ($month = 0; $month < 12; $month++) {
            $at = $from->modify("+{$month} months");
            if ($za->getOffset($at) !== $zb->getOffset($at)) {
                return false;
            }
        }

        return true;
    }
}
