<?php

namespace Modules\Learning\Digest;

/**
 * The weekly note in the facts' own words (learning::digest.facts), when no
 * helper writes it or what it wrote may not go out: one line of plain parts,
 * about the child as {child}, in each language.
 */
final class FactsText
{
    /** @param  array<string, mixed>  $facts  WeekFacts::for() */
    public static function for(array $facts, string $locale): string
    {
        $t = fn (string $key, array $replace = []) => __('learning::digest.facts.'.$key, $replace, $locale);
        $list = fn (array $titles) => implode($t('and'), array_map(fn (array $title) => $t('quoted', ['title' => $title[$locale] ?? $title['ar']]), $titles));
        $parts = [$t('intro')];

        ['done' => $done, 'missed' => $missed] = $facts['lessons'];
        if ($done + $missed > 0) {
            $parts[] = $missed > 0 ? $t('lessons_missed', ['done' => $done, 'missed' => $missed]) : $t('lessons', ['done' => $done]);
        }
        foreach (['mastered', 'worked_on', 'review'] as $kind) {
            if ($facts['learned'][$kind] !== []) {
                $parts[] = $t($kind, ['lessons' => $list($facts['learned'][$kind])]);
            }
        }
        if ($facts['answers'] !== []) {
            $parts[] = implode($t('and'), array_map(fn (array $a) => $a[$locale] ?? $a['ar'], $facts['answers'])).'.';
        }
        $homework = array_filter($facts['homework']);
        if ($homework !== []) {
            $parts[] = $t('homework', ['parts' => implode($t('and'), array_map(fn (string $k, int $n) => $t('homework_'.$k, ['n' => $n]), array_keys($homework), $homework))]);
        }
        if ($facts['practice']['rounds'] > 0) {
            $parts[] = $t('practice', ['rounds' => $facts['practice']['rounds']]);
        }
        if (($facts['stars'] ?? 0) > 0) {
            $parts[] = $t('stars', ['stars' => $facts['stars']]);
        }
        if ($facts['notes'] !== []) {
            $parts[] = $t('note', ['note' => $facts['notes'][0]]);
        }

        return implode(' ', $parts);
    }
}
