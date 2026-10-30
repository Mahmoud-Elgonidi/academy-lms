<?php

namespace Modules\Curriculum\Domain\Outline;

/**
 * An outline draft: the rows every quick way to start a curriculum hands
 * back (the helper, a pasted list or a sheet, a template), shown in the
 * review and saved by ApplyOutline. Pure: lengths, counts and the shape of
 * the tree, nothing read from anywhere.
 *
 * A row is a level (depth 0), a unit in the level above it (depth 1), or a
 * lesson straight in the level (depth 1) or in the unit above it (depth 2):
 * {kind, depth, title_ar, title_en, objective, quran: ?{surah, from, to},
 * on, key}. The order of the rows is the teaching order, so the tree is
 * exactly what the rows say. A row left unticked (on false) is left out with
 * everything under it.
 */
final class OutlineRules
{
    public const LEVELS_MAX = 12;

    public const UNITS_MAX = 120;

    public const LESSONS_MAX = 300;

    public const TITLE_MAX = 200;

    public const OBJECTIVE_MAX = 1000;

    /**
     * The rows made whole: each trimmed and cut to its lengths, a title in both
     * languages (the other's where one is empty), the depth its place allows (a
     * lesson with no unit open sits in the level), and the counts held to the
     * maxima. What has no title, no level above it, or no room is dropped, with
     * what is under it.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{rows: list<array<string, mixed>>, dropped: int}
     */
    public static function clean(array $rows): array
    {
        $out = [];
        $dropped = 0;
        $levels = $units = $lessons = 0;
        $levelOpen = false;
        $unitOpen = false;
        $skipping = null;   // 'level' or 'unit': one was dropped, and so is what is under it

        foreach (array_values($rows) as $i => $raw) {
            $row = is_array($raw) ? self::row($raw, $i) : null;
            if ($row !== null && $skipping !== null) {
                $ends = $row['kind'] === 'level' || ($skipping === 'unit' && ($row['kind'] === 'unit' || $row['depth'] === 1));
                if ($ends) {
                    $skipping = null;
                } else {
                    $row = null;
                }
            }
            if ($row === null) {
                $dropped++;

                continue;
            }

            if ($row['kind'] === 'level') {
                if ($levels >= self::LEVELS_MAX) {
                    [$levelOpen, $unitOpen, $skipping] = [false, false, 'level'];
                    $dropped++;

                    continue;
                }
                $levels++;
                [$levelOpen, $unitOpen] = [true, false];
            } elseif ($row['kind'] === 'unit') {
                if (! $levelOpen || $units >= self::UNITS_MAX) {
                    [$unitOpen, $skipping] = [false, 'unit'];
                    $dropped++;

                    continue;
                }
                $units++;
                $unitOpen = true;
            } else {
                if (! $levelOpen || $lessons >= self::LESSONS_MAX) {
                    $dropped++;

                    continue;
                }
                $row['depth'] = $row['depth'] === 2 && $unitOpen ? 2 : 1;
                if ($row['depth'] === 1) {
                    $unitOpen = false;   // a lesson straight in the level closes the unit before it
                }
                $lessons++;
            }
            $out[] = $row;
        }

        return ['rows' => $out, 'dropped' => $dropped];
    }

    /**
     * The rows that count: ticked, under ticked parents.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function selected(array $rows): array
    {
        $out = [];
        foreach (self::counting($rows) as $i => $counts) {
            if ($counts) {
                $out[] = $rows[$i];
            }
        }

        return $out;
    }

    /**
     * For each row, whether it counts (it is ticked and so is every row above it in the tree).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<bool>
     */
    public static function counting(array $rows): array
    {
        $out = [];
        $open = [true, true, true];   // whether the last row at each depth counts
        foreach (array_values($rows) as $row) {
            $depth = max(0, min(2, (int) ($row['depth'] ?? 0)));
            $on = ($row['on'] ?? true) !== false && ($depth === 0 || $open[$depth - 1]);
            $open[$depth] = $on;
            for ($d = $depth + 1; $d <= 2; $d++) {
                $open[$d] = $on;
            }
            $out[] = $on;
        }

        return $out;
    }

    /**
     * Where the row sits: the index of the level or unit it belongs to, null for a level.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<?int>
     */
    public static function parents(array $rows): array
    {
        $out = [];
        $last = [null, null];   // the index of the last level and unit
        foreach (array_values($rows) as $i => $row) {
            $depth = (int) ($row['depth'] ?? 0);
            $out[] = $depth === 0 ? null : ($depth === 2 ? $last[1] : $last[0]);
            if (($row['kind'] ?? '') === 'level') {
                $last = [$i, null];
            } elseif (($row['kind'] ?? '') === 'unit') {
                $last[1] = $i;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{levels: int, units: int, lessons: int}
     */
    public static function counts(array $rows): array
    {
        $kinds = array_count_values(array_map(fn ($r) => (string) ($r['kind'] ?? ''), $rows));

        return ['levels' => $kinds['level'] ?? 0, 'units' => $kinds['unit'] ?? 0, 'lessons' => $kinds['lesson'] ?? 0];
    }

    /**
     * Why the rows may not be added to a version that already has $levels levels and $lessons
     * lessons, or null: nothing to add (empty), or over the maxima (levels, lessons).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public static function refusal(array $rows, int $levels = 0, int $lessons = 0): ?string
    {
        $counts = self::counts($rows);

        return match (true) {
            $counts['levels'] + $counts['units'] + $counts['lessons'] === 0 => 'empty',
            $levels + $counts['levels'] > self::LEVELS_MAX => 'levels',
            $lessons + $counts['lessons'] > self::LESSONS_MAX => 'lessons',
            default => null,
        };
    }

    /**
     * Index $i and the rows under it in the tree.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<int>
     */
    public static function branch(array $rows, int $i): array
    {
        if (! isset($rows[$i])) {
            return [];
        }
        $depth = (int) ($rows[$i]['depth'] ?? 0);
        $kind = (string) ($rows[$i]['kind'] ?? '');
        $out = [$i];
        if ($kind === 'lesson') {
            return $out;
        }
        for ($j = $i + 1; isset($rows[$j]); $j++) {
            $next = $rows[$j];
            if (($next['kind'] ?? '') === 'level' || ($kind === 'unit' && ($next['kind'] ?? '') === 'unit') || (int) ($next['depth'] ?? 0) <= $depth) {
                break;
            }
            $out[] = $j;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return ?array<string, mixed>
     */
    private static function row(array $raw, int $i): ?array
    {
        $kind = $raw['kind'] ?? null;
        if (! in_array($kind, ['level', 'unit', 'lesson'], true)) {
            return null;
        }
        $ar = self::cut((string) ($raw['title_ar'] ?? ''), self::TITLE_MAX);
        $en = self::cut((string) ($raw['title_en'] ?? ''), self::TITLE_MAX);
        if ($ar === '' && $en === '') {
            return null;
        }
        $objective = self::cut(str_replace("\r\n", "\n", (string) ($raw['objective'] ?? '')), self::OBJECTIVE_MAX);

        return [
            'kind' => $kind,
            'depth' => match ($kind) {
                'level' => 0,
                'unit' => 1,
                'lesson' => (int) ($raw['depth'] ?? 1) >= 2 ? 2 : 1,
            },
            'title_ar' => $ar !== '' ? $ar : $en,
            'title_en' => $en !== '' ? $en : $ar,
            'objective' => $kind === 'lesson' && $objective !== '' ? $objective : null,
            'quran' => $kind === 'lesson' ? self::quran($raw['quran'] ?? null) : null,
            'on' => ($raw['on'] ?? true) !== false,
            'key' => is_string($raw['key'] ?? null) && $raw['key'] !== '' ? $raw['key'] : 'r'.$i,
        ];
    }

    /** @return ?array{surah: int, from: int, to: int} a passage by numbers, in its shape (the platform's copy checks the rest) */
    private static function quran(mixed $given): ?array
    {
        if (! is_array($given)) {
            return null;
        }
        [$surah, $from, $to] = [(int) ($given['surah'] ?? 0), (int) ($given['from'] ?? 0), (int) ($given['to'] ?? 0)];

        return $surah >= 1 && $surah <= 114 && $from >= 1 && $to >= $from ? ['surah' => $surah, 'from' => $from, 'to' => $to] : null;
    }

    private static function cut(string $text, int $max): string
    {
        $text = trim(preg_replace('/[ \t]+/u', ' ', $text) ?? '');

        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max)) : $text;
    }
}
