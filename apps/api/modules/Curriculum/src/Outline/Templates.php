<?php

namespace Modules\Curriculum\Outline;

/**
 * Ready outlines to start a curriculum from, in both languages (wording in
 * curriculum::templates), each edited in the review before it is saved:
 * the 28 Arabic letters, and English from A1 to C1 in general units.
 */
final class Templates
{
    public const KEYS = ['arabic_letters', 'english_cefr'];

    private const LETTERS = ['ا', 'ب', 'ت', 'ث', 'ج', 'ح', 'خ', 'د', 'ذ', 'ر', 'ز', 'س', 'ش', 'ص', 'ض', 'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ك', 'ل', 'م', 'ن', 'ه', 'و', 'ي'];

    /** @return array<string, string> key => name, in the reader's language, those offered now */
    public function options(): array
    {
        $out = [];
        foreach (self::KEYS as $key) {
            $out[$key] = (string) __('curriculum::templates.'.$key.'.name');
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
}
