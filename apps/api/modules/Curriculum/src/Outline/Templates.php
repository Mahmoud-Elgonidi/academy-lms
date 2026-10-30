<?php

namespace Modules\Curriculum\Outline;

use Modules\Curriculum\Texts\QuranText;

/**
 * Ready outlines to start a curriculum from, in both languages (wording in
 * curriculum::templates), each edited in the review before it is saved:
 * Juz Amma (a lesson a surah from An-Nas back to An-Naba, its names and
 * verses from the platform's Quran metadata, never typed, its text the whole
 * surah; offered only while the platform has its copy), the 28 Arabic
 * letters, English from A1 to C1 in general units, and the Noorani Qaida.
 */
final class Templates
{
    public const KEYS = ['juz_amma', 'arabic_letters', 'english_cefr', 'noorani'];

    private const LETTERS = ['ا', 'ب', 'ت', 'ث', 'ج', 'ح', 'خ', 'د', 'ذ', 'ر', 'ز', 'س', 'ش', 'ص', 'ض', 'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ك', 'ل', 'م', 'ن', 'ه', 'و', 'ي'];

    /** Juz Amma's surahs: An-Naba (78) to An-Nas (114). */
    private const JUZ_AMMA = [78, 114];

    public function __construct(private readonly QuranText $quran) {}

    /** @return array<string, string> key => name, in the reader's language, those offered now */
    public function options(): array
    {
        $out = [];
        foreach (self::KEYS as $key) {
            if ($key !== 'juz_amma' || $this->juzAmma() !== []) {
                $out[$key] = (string) __('curriculum::templates.'.$key.'.name');
            }
        }

        return $out;
    }

    /** @return array<string, string> key => what it holds */
    public function hints(): array
    {
        return array_map(fn (string $key) => (string) __('curriculum::templates.'.$key.'.hint'), array_combine(array_keys($this->options()), array_keys($this->options())));
    }

    /** @return list<array<string, mixed>> the template's outline rows; empty for one not offered */
    public function rows(string $key): array
    {
        $t = 'curriculum::templates.'.$key.'.';
        $both = fn (string $line, array $replace = [], ?array $en = null) => [
            'title_ar' => (string) __($t.$line, $replace, 'ar'),
            'title_en' => (string) __($t.$line, $en ?? $replace, 'en'),
        ];
        $level = fn (array $replace = []) => ['kind' => 'level', 'depth' => 0] + $both('level', $replace);

        return match ($key) {
            'juz_amma' => $this->juzAmma() === [] ? [] : [
                $level(),
                ...array_map(fn (array $s) => ['kind' => 'lesson', 'depth' => 1]
                    + $both('lesson', ['name' => $s['name_ar']], ['name' => $s['name_en']])
                    + ['objective' => (string) __($t.'objective', ['name' => $s['name_ar']], 'ar'), 'quran' => ['surah' => $s['number'], 'from' => 1, 'to' => $s['verses']]],
                    array_reverse($this->juzAmma())),
            ],
            'arabic_letters' => [
                $level(),
                ...array_map(function (int $i) use ($t, $both) {
                    $ar = (array) __($t.'letters', [], 'ar');
                    $en = (array) __($t.'letters', [], 'en');

                    return ['kind' => 'lesson', 'depth' => 1]
                        + $both('lesson', ['name' => $ar[$i] ?? '', 'letter' => self::LETTERS[$i]], ['name' => $en[$i] ?? '', 'letter' => self::LETTERS[$i]])
                        + ['objective' => (string) __($t.'objective', ['name' => $ar[$i] ?? ''], 'ar')];
                }, array_keys(self::LETTERS)),
            ],
            'english_cefr' => $this->english(),
            'noorani' => [
                $level(),
                ...array_map(fn (string $ar, string $en) => ['kind' => 'lesson', 'depth' => 1, 'title_ar' => $ar, 'title_en' => $en],
                    (array) __($t.'lessons', [], 'ar'), (array) __($t.'lessons', [], 'en')),
            ],
            default => [],
        };
    }

    /** @return list<array<string, mixed>> */
    private function english(): array
    {
        $t = 'curriculum::templates.english_cefr.';
        $skills = ['ar' => (array) __($t.'skills', [], 'ar'), 'en' => (array) __($t.'skills', [], 'en')];
        $topicsEn = (array) __($t.'levels', [], 'en');
        $rows = [];
        foreach ((array) __($t.'levels', [], 'ar') as $code => $topics) {
            $rows[] = ['kind' => 'level', 'depth' => 0, 'title_ar' => __($t.'level', ['code' => $code], 'ar'), 'title_en' => __($t.'level', ['code' => $code], 'en')];
            foreach ((array) $topics as $i => $topic) {
                $topicEn = (string) ($topicsEn[$code][$i] ?? $topic);
                $rows[] = ['kind' => 'unit', 'depth' => 1, 'title_ar' => $topic, 'title_en' => $topicEn];
                foreach ($skills['ar'] as $j => $skill) {
                    $rows[] = ['kind' => 'lesson', 'depth' => 2,
                        'title_ar' => __($t.'lesson', ['skill' => $skill, 'topic' => $topic], 'ar'),
                        'title_en' => __($t.'lesson', ['skill' => $skills['en'][$j] ?? $skill, 'topic' => $topicEn], 'en')];
                }
            }
        }

        return $rows;
    }

    /** @return list<array{number: int, name_ar: string, name_en: string, verses: int}> Juz Amma's surahs as the platform's copy has them, none while it lacks any */
    private function juzAmma(): array
    {
        $surahs = array_values(array_filter($this->quran->surahs(), fn (array $s) => $s['number'] >= self::JUZ_AMMA[0] && $s['number'] <= self::JUZ_AMMA[1]));

        return count($surahs) === self::JUZ_AMMA[1] - self::JUZ_AMMA[0] + 1 ? $surahs : [];
    }
}
