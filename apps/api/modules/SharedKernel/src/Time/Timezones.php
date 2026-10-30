<?php

namespace Modules\SharedKernel\Time;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use IntlDateFormatter;
use Throwable;

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

    /** The regions of the zone pickers, in the order they are listed (nearest the academies first). */
    public const REGIONS = ['Asia', 'Africa', 'Europe', 'America', 'Australia', 'Indian', 'Atlantic', 'Pacific', 'Antarctica', 'Arctic', 'UTC'];

    /** @var array<string, array<string, string>> the cities ICU names, by locale and zone */
    private static array $cities = [];

    /**
     * The city of an IANA zone in the current language (or the one given): "Africa/Cairo" → "القاهرة".
     * Our own names first (shared::cities), then the exemplar city ICU knows in that language, then the IANA name.
     */
    public static function city(string $timezone, ?string $locale = null): string
    {
        $key = 'shared::cities.'.$timezone;
        $name = __($key, [], $locale);
        if ($name !== $key) {
            return $name;
        }
        $locale ??= app()->getLocale();
        self::$cities[$locale] ??= self::cities($locale);

        return self::$cities[$locale][$timezone] ?? str_replace('_', ' ', substr(strrchr($timezone, '/') ?: '/'.$timezone, 1) ?: $timezone);
    }

    /** How far a zone is from UTC at that moment (now by default), as people read it: "UTC+2", "UTC+5:30", "UTC−3". */
    public static function offset(string $timezone, ?DateTimeInterface $at = null): string
    {
        $seconds = (new DateTimeZone($timezone))->getOffset($at ?? CarbonImmutable::now());
        $hours = intdiv(abs($seconds), 3600);
        $minutes = intdiv(abs($seconds) % 3600, 60);

        return 'UTC'.($seconds < 0 ? '−' : '+').$hours.($minutes > 0 ? ':'.str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) : '');
    }

    /** A zone as people read it: "القاهرة (UTC+2)", in the current language (or the one given); UTC alone is "UTC". */
    public static function label(string $timezone, ?string $locale = null, ?DateTimeInterface $at = null): string
    {
        return self::join(self::city($timezone, $locale), self::offset($timezone, $at));
    }

    /** The region a zone is listed under ("Africa/Cairo" → "Africa"; "UTC" → "UTC"). */
    public static function region(string $timezone): string
    {
        $region = strstr($timezone, '/', true);

        return $region === false || ! in_array($region, self::REGIONS, true) ? 'UTC' : $region;
    }

    /**
     * Every zone for a picker, sorted by region, then by how far it is from UTC, then by city:
     * the zone's IANA name, its city and offset now, the two as one label, and its region's name,
     * all in the current language (or the one given).
     *
     * @return list<ZoneOption>
     */
    public static function catalog(?string $locale = null): array
    {
        $now = CarbonImmutable::now();
        $rows = [];
        foreach (self::all() as $id) {
            $region = self::region($id);
            $city = self::city($id, $locale);
            $offset = self::offset($id, $now);
            $rows[] = [
                'sort' => [array_search($region, self::REGIONS, true), (new DateTimeZone($id))->getOffset($now), $city],
                'row' => new ZoneOption($id, $city, $offset, self::join($city, $offset), __('shared::time.regions.'.$region, [], $locale)),
            ];
        }
        usort($rows, fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return array_column($rows, 'row');
    }

    /** The same zones for a Select: region name => [IANA name => "القاهرة (UTC+2)"]. @return array<string, array<string, string>> */
    public static function options(?string $locale = null): array
    {
        $groups = [];
        foreach (self::catalog($locale) as $zone) {
            $groups[$zone->region][$zone->id] = $zone->label;
        }

        return $groups;
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

    private static function join(string $city, string $offset): string
    {
        return $city === 'UTC' ? $city : $city.' ('.$offset.')';
    }

    /**
     * The city ICU writes for every zone in that language: worked out once per
     * language, ICU and tz data (a few hundred formatters), then kept in the cache.
     *
     * @return array<string, string>
     */
    private static function cities(string $locale): array
    {
        $key = 'shared.timezone_cities.'.$locale.'.'.(defined('INTL_ICU_VERSION') ? INTL_ICU_VERSION : 'none').'.'.timezone_version_get();

        return Cache::rememberForever($key, function () use ($locale): array {
            $names = [];
            foreach (self::all() as $zone) {
                if (($name = self::exemplar($zone, $locale)) !== null) {
                    $names[$zone] = $name;
                }
            }

            return $names;
        });
    }

    /** The city ICU writes for a zone in that language, when it knows one. */
    private static function exemplar(string $timezone, string $locale): ?string
    {
        if (! class_exists(IntlDateFormatter::class) || ! str_contains($timezone, '/') || str_starts_with($timezone, 'Etc/')) {
            return null;
        }
        try {
            $name = (new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $timezone, null, 'VVV'))->format(0);
            $unknown = (new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'Etc/Unknown', null, 'VVV'))->format(0);
        } catch (Throwable) {
            return null;   // a zone newer than the server's ICU
        }

        return is_string($name) && $name !== '' && $name !== $unknown ? $name : null;
    }
}
