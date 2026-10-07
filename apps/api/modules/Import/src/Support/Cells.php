<?php

namespace Modules\Import\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Values as academies type them in a sheet, read into what the platform
 * keeps; null when a value cannot be read (the caller says which).
 */
final class Cells
{
    private const DAYS = [
        1 => ['الاثنين', 'الاتنين', 'اثنين', 'اتنين', 'monday', 'mon'],
        2 => ['الثلاثاء', 'الثلاث', 'التلات', 'ثلاثاء', 'تلات', 'tuesday', 'tue', 'tues'],
        3 => ['الاربعاء', 'الاربع', 'اربعاء', 'اربع', 'wednesday', 'wed'],
        4 => ['الخميس', 'خميس', 'thursday', 'thu', 'thur', 'thurs'],
        5 => ['الجمعه', 'جمعه', 'friday', 'fri'],
        6 => ['السبت', 'سبت', 'saturday', 'sat'],
        7 => ['الاحد', 'احد', 'الحد', 'sunday', 'sun'],
    ];

    public static function gender(string $v): ?string
    {
        return match (Text::key($v)) {
            'ذكر', 'male', 'm', 'رجل', 'مدرس', 'استاذ', 'ولد', 'boy' => 'male',
            'انثي', 'female', 'f', 'ست', 'سيده', 'مدرسه', 'استاذه', 'بنت', 'girl' => 'female',
            default => null,
        };
    }

    /** Yes or no; $default when empty, null when unreadable. */
    public static function yesNo(string $v, bool $default): ?bool
    {
        return match (Text::key($v)) {
            '' => $default,
            'نعم', 'ايوه', 'اه', 'yes', 'y', 'true', '1', 'صح', '✓' => true,
            'لا', 'no', 'n', 'false', '0', 'لاء' => false,
            default => null,
        };
    }

    /** "2015-03-20", "20/3/2015", "2015" (a year: 1 July), or a date cell. */
    public static function date(string $v): ?CarbonImmutable
    {
        $v = trim(Text::digits($v));
        try {
            return match (true) {
                (bool) preg_match('/^\d{4}$/', $v) => CarbonImmutable::create((int) $v, 7, 1),
                (bool) preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $v, $m) => CarbonImmutable::create((int) $m[1], (int) $m[2], (int) $m[3]),
                (bool) preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $v, $m) => CarbonImmutable::create((int) $m[3], (int) $m[2], (int) $m[1]),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    public static function integer(string $v): ?int
    {
        $v = trim(Text::digits($v));

        return preg_match('/^\d+$/', $v) ? (int) $v : null;
    }

    /** Lesson minutes: "30", "45 دقيقة", "ساعة", "1.5 ساعة", "60 min". */
    public static function minutes(string $v): ?int
    {
        $v = Text::digits(mb_strtolower(trim($v)));
        $hours = str_contains($v, 'ساع') || str_contains($v, 'hour') || str_contains($v, 'hr');
        if (preg_match('/(\d+(?:[.,]\d+)?)/', $v, $m)) {
            $n = (float) str_replace(',', '.', $m[1]);

            return (int) round($hours ? $n * 60 : $n);
        }

        return $hours ? 60 : null;
    }

    /**
     * Weekly times: "الأحد 17:00، الأربعاء 5:30 م", "Sun 17:00; Wed 5pm",
     * "السبت والثلاثاء 18:00" (one hour for several days).
     *
     * @return list<array{weekday: int, time: string}>|null
     */
    public static function weeklyTimes(string $v): ?array
    {
        $v = Text::digits(trim($v));
        if ($v === '') {
            return null;
        }
        $times = [];
        foreach (preg_split('/[,،;؛|\n]+/u', $v) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $clock = self::clock($part);
            $days = self::days($part);
            if ($clock === null || $days === []) {
                return null;
            }
            foreach ($days as $day) {
                $times[] = ['weekday' => $day, 'time' => $clock];
            }
        }

        return $times === [] ? null : $times;
    }

    /** @return list<int> the ISO weekdays named in the text */
    private static function days(string $part): array
    {
        $words = preg_split('/[\s]+|(?<=\S)و(?=ال)/u', preg_replace('/\d+([:.]\d+)?\s*(ص|م|am|pm|صباحا|مساء|مساءا|ظهرا|عصرا)?/iu', ' ', $part) ?? $part) ?: [];
        $found = [];
        foreach ($words as $word) {
            $key = Text::key(preg_replace('/^و(?=ال|[ا-ي]{3,})/u', '', trim($word)) ?? $word);
            if ($key === '') {
                continue;
            }
            foreach (self::DAYS as $iso => $names) {
                if (in_array($key, array_map(fn (string $n) => Text::key($n), $names), true)) {
                    $found[] = $iso;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /** "17:00", "5:30 م", "5pm", "17.30" → "HH:MM". */
    private static function clock(string $part): ?string
    {
        if (! preg_match('/(\d{1,2})(?:[:.](\d{2}))?\s*(ص|م|am|pm|a\.m\.|p\.m\.|صباحا|صباحًا|مساء|مساءً|مساءا|ظهرا|ظهرًا|عصرا|عصرًا)?/iu', $part, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
        $suffix = mb_strtolower($m[3] ?? '');
        $pm = in_array($suffix, ['م', 'pm', 'p.m.', 'مساء', 'مساءً', 'مساءا', 'عصرا', 'عصرًا'], true) || (in_array($suffix, ['ظهرا', 'ظهرًا'], true) && $h < 12);
        $am = in_array($suffix, ['ص', 'am', 'a.m.', 'صباحا', 'صباحًا'], true);
        if ($pm && $h < 12) {
            $h += 12;
        } elseif ($am && $h === 12) {
            $h = 0;
        }

        return $h < 24 && $min < 60 ? sprintf('%02d:%02d', $h, $min) : null;
    }
}
