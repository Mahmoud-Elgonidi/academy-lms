<?php

namespace Modules\Curriculum\Outline;

/**
 * A pasted list or a sheet read as an outline draft (Domain\Outline\OutlineRules
 * rows). Pure: text in, rows out.
 *
 * A pasted line's depth comes from how it starts: its indentation (two spaces
 * or a tab a step), its dashes ("-" one step, "--" two; a bullet "•" or "*"
 * counts as a dash), or its numbering ("1." one, "1.1" two, "1.1.1" three),
 * measured from the shallowest line. The first depth is a level; a line
 * under it is a unit when lines sit under it in turn, else a lesson; the
 * third depth is a lesson. A list of one depth is the lessons of one level.
 * "title | objective" (or a tab between them) gives a lesson its objective.
 *
 * A sheet's columns are level, unit, lesson and an optional objective: a
 * level carries down to the rows under it, and so does a unit within its
 * level, so each lesson needs only its own cell.
 */
final class TextOutline
{
    /** @return list<array<string, mixed>> */
    public static function parse(string $text, string $levelTitle): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', str_replace("\u{FEFF}", '', $text)) ?: [] as $line) {
            $read = self::line($line);
            if ($read !== null) {
                $lines[] = $read;
            }
        }
        if ($lines === []) {
            return [];
        }
        $min = min(array_column($lines, 'depth'));
        foreach ($lines as &$line) {
            $line['depth'] = min(3, $line['depth'] - $min + 1);
        }
        unset($line);

        // One depth only: these are lessons, of a level named for them.
        if (max(array_column($lines, 'depth')) === 1) {
            return [
                self::row('level', 0, $levelTitle),
                ...array_map(fn (array $l) => self::row('lesson', 1, $l['title'], $l['objective']), $lines),
            ];
        }

        $rows = [];
        $unitOpen = false;
        foreach ($lines as $i => $line) {
            $next = $lines[$i + 1]['depth'] ?? 0;
            if ($line['depth'] === 1) {
                $rows[] = self::row('level', 0, $line['title']);
                $unitOpen = false;
            } elseif ($line['depth'] === 2 && $next > 2) {
                $rows[] = self::row('unit', 1, $line['title']);
                $unitOpen = true;
            } elseif ($line['depth'] === 2) {
                $rows[] = self::row('lesson', 1, $line['title'], $line['objective']);
                $unitOpen = false;
            } else {
                $rows[] = self::row('lesson', $unitOpen ? 2 : 1, $line['title'], $line['objective']);
            }
        }

        return $rows;
    }

    /**
     * @param  list<list<string>>  $cells  the sheet's rows (level, unit, lesson, objective)
     * @param  list<string>  $headers  words a title row may hold, in any language: such a first row is skipped
     * @return list<array<string, mixed>>
     */
    public static function fromSheet(array $cells, array $headers = []): array
    {
        $headers = array_map(fn ($h) => mb_strtolower(trim($h)), $headers);
        $rows = [];
        [$level, $unit] = [null, null];
        foreach (array_values($cells) as $i => $row) {
            $row = array_map(fn ($c) => trim((string) $c), array_values($row));
            [$l, $u, $lesson, $objective] = array_pad(array_slice($row, 0, 4), 4, '');
            if ($i === 0 && count(array_intersect(array_map('mb_strtolower', [$l, $u, $lesson]), $headers)) >= 2) {
                continue;
            }
            if ($l !== '' && $l !== $level) {
                $rows[] = self::row('level', 0, $l);
                [$level, $unit] = [$l, null];
            }
            if ($level === null) {
                continue;
            }
            if ($u !== '' && $u !== $unit) {
                $rows[] = self::row('unit', 1, $u);
                $unit = $u;
            }
            if ($lesson !== '') {
                $rows[] = self::row('lesson', $unit !== null ? 2 : 1, $lesson, $objective !== '' ? $objective : null);
            }
        }

        return $rows;
    }

    /** @return ?array{depth: int, title: string, objective: ?string} */
    private static function line(string $line): ?array
    {
        if (trim($line) === '') {
            return null;
        }
        // Indentation: two spaces or a tab a step.
        preg_match('/^[ \t\x{00A0}]*/u', $line, $m);
        $indent = str_replace(["\t", "\u{00A0}"], ['  ', ' '], $m[0]);
        $depth = 1 + intdiv(strlen($indent), 2);
        $rest = mb_substr($line, mb_strlen($m[0]));

        // Numbering ("1.", "1)", "1.2", "1.2.3", in either kind of digits), else dashes or bullets.
        if (preg_match('/^([0-9٠-٩]+(?:\.[0-9٠-٩]+)+)[.)]?\s+(.+)$/u', $rest, $n) || preg_match('/^([0-9٠-٩]+)[.)]\s*(.+)$/u', $rest, $n)) {
            $depth = count(preg_split('/\./u', $n[1]) ?: [1]);
            $rest = $n[2];
        } elseif (preg_match('/^([-–•*]+)\s*(.+)$/u', $rest, $d)) {
            $depth += mb_strlen($d[1]);
            $rest = $d[2];
        }

        [$title, $objective] = array_pad(preg_split('/\s*(?:\||\t)\s*/u', trim($rest), 2) ?: [''], 2, null);
        $title = trim((string) $title);

        return $title === '' ? null : ['depth' => $depth, 'title' => $title, 'objective' => $objective !== null && trim($objective) !== '' ? trim($objective) : null];
    }

    /** @return array<string, mixed> */
    private static function row(string $kind, int $depth, string $title, ?string $objective = null): array
    {
        return ['kind' => $kind, 'depth' => $depth, 'title_ar' => $title, 'title_en' => $title, 'objective' => $objective, 'quran' => null, 'on' => true];
    }
}
