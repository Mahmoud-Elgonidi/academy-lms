<?php

namespace Modules\Curriculum\Outline;

use Modules\Curriculum\Domain\Outline\OutlineRules;

/**
 * Turns the model's outline into rows to review (OutlineRules); a level or
 * unit with no title falls back to its number. Nothing is saved.
 */
final class OutlineAnswer
{
    /**
     * @param  array<string, mixed>  $data  the model's answer
     * @return array{rows: list<array<string, mixed>>, dropped: int}
     */
    public static function read(array $data): array
    {
        $rows = [];
        $dropped = 0;
        $own = fn (mixed $text): string => trim(is_string($text) ? $text : '');
        $titles = function (array $raw, string $fallback, int $n) use ($own): array {
            [$ar, $en] = [$own($raw['title_ar'] ?? ''), $own($raw['title_en'] ?? '')];

            return $ar === '' && $en === ''
                ? ['title_ar' => __('curriculum::outline.fallback.'.$fallback, ['n' => $n], 'ar'), 'title_en' => __('curriculum::outline.fallback.'.$fallback, ['n' => $n], 'en')]
                : ['title_ar' => $ar, 'title_en' => $en];
        };
        $lesson = function (mixed $raw, int $depth) use (&$dropped, $own): ?array {
            if (! is_array($raw)) {
                $dropped++;

                return null;
            }

            return ['kind' => 'lesson', 'depth' => $depth, 'objective' => $own($raw['objective'] ?? '') ?: null,
                'title_ar' => trim((string) ($raw['title_ar'] ?? '')), 'title_en' => trim((string) ($raw['title_en'] ?? ''))];
        };

        $units = 0;
        foreach (array_values((array) ($data['levels'] ?? [])) as $li => $level) {
            if (! is_array($level)) {
                $dropped++;

                continue;
            }
            $rows[] = ['kind' => 'level', 'depth' => 0] + $titles($level, 'level', $li + 1);
            foreach (array_values((array) ($level['lessons'] ?? [])) as $raw) {
                if (($row = $lesson($raw, 1)) !== null) {
                    $rows[] = $row;
                }
            }
            foreach (array_values((array) ($level['units'] ?? [])) as $unit) {
                if (! is_array($unit)) {
                    $dropped++;

                    continue;
                }
                $rows[] = ['kind' => 'unit', 'depth' => 1] + $titles($unit, 'unit', ++$units);
                foreach (array_values((array) ($unit['lessons'] ?? [])) as $raw) {
                    if (($row = $lesson($raw, 2)) !== null) {
                        $rows[] = $row;
                    }
                }
            }
        }

        $clean = OutlineRules::clean($rows);

        return ['rows' => $clean['rows'], 'dropped' => $dropped + $clean['dropped']];
    }
}
