<?php

namespace Modules\Curriculum\Outline;

use Modules\Curriculum\Contracts\DTOs\TextSource;
use Modules\Curriculum\Domain\Outline\OutlineRules;
use Modules\Curriculum\Texts\QuranText;

/**
 * Turns the model's outline into rows to review (OutlineRules). For the
 * Quran, a lesson is kept only as numbers the platform's copy has (the
 * surah, verses within it, at most TextSource::VERSES_MAX): its titles are
 * the surah's names from the copy and the verses, and its text the passage;
 * an objective, level or unit title that writes three of the Quran's words
 * itself is left out (a title falls back to its number). Nothing is saved.
 */
final class OutlineAnswer
{
    /**
     * @param  array<string, mixed>  $data  the model's answer
     * @return array{rows: list<array<string, mixed>>, dropped: int}
     */
    public static function read(array $data, QuranText $quran, bool $isQuran): array
    {
        $rows = [];
        $dropped = 0;
        $own = fn (mixed $text): string => ($t = trim(is_string($text) ? $text : '')) !== '' && $isQuran && $quran->quotes($t) ? '' : $t;
        $titles = function (array $raw, string $fallback, int $n) use ($own): array {
            [$ar, $en] = [$own($raw['title_ar'] ?? ''), $own($raw['title_en'] ?? '')];

            return $ar === '' && $en === ''
                ? ['title_ar' => __('curriculum::outline.fallback.'.$fallback, ['n' => $n], 'ar'), 'title_en' => __('curriculum::outline.fallback.'.$fallback, ['n' => $n], 'en')]
                : ['title_ar' => $ar, 'title_en' => $en];
        };
        $lesson = function (mixed $raw, int $depth) use (&$dropped, $own, $quran, $isQuran): ?array {
            if (! is_array($raw)) {
                $dropped++;

                return null;
            }
            $row = ['kind' => 'lesson', 'depth' => $depth, 'objective' => $own($raw['objective'] ?? '') ?: null];
            if (! $isQuran) {
                return $row + ['title_ar' => trim((string) ($raw['title_ar'] ?? '')), 'title_en' => trim((string) ($raw['title_en'] ?? ''))];
            }
            $surah = $quran->surah((int) ($raw['surah'] ?? 0));
            [$from, $to] = [(int) ($raw['from'] ?? 0), (int) ($raw['to'] ?? 0)];
            if ($surah === null || $from < 1 || $to < $from || $to > $surah['verses'] || $to - $from + 1 > TextSource::VERSES_MAX) {
                $dropped++;

                return null;
            }
            $whole = $from === 1 && $to === $surah['verses'];
            $line = 'curriculum::outline.quran_lesson.'.($whole ? 'whole' : 'part');

            return $row + [
                'title_ar' => __($line, ['name' => $surah['name_ar'], 'from' => $from, 'to' => $to], 'ar'),
                'title_en' => __($line, ['name' => $surah['name_en'], 'from' => $from, 'to' => $to], 'en'),
                'quran' => ['surah' => $surah['number'], 'from' => $from, 'to' => $to],
            ];
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
