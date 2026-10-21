<?php

namespace Modules\Learning\Assist;

/**
 * How a check did against mistakes a person marked by ear (pure): of the
 * words marked missed or wrong, which the check caught (and with the same
 * kind), which it missed, and which it marked that the person did not. A
 * word is its verse and its place in the verse, from 1.
 */
final class RecitationScore
{
    /**
     * @param  list<array{verse: ?int, words: list<array{state: string}>}>  $verses  the check's verses
     * @param  list<array{verse: int, word: int, kind: string}>  $known  the mistakes marked by ear (kind: missing or wrong)
     * @return array{caught: int, same_kind: int, missed: list<string>, false_alarms: list<string>}
     */
    public static function of(array $verses, array $known): array
    {
        $flagged = [];
        foreach ($verses as $v) {
            foreach ($v['words'] as $i => $w) {
                if ($w['state'] !== 'ok') {
                    $flagged[($v['verse'] ?? 0).':'.($i + 1)] = $w['state'];
                }
            }
        }
        $marked = [];
        foreach ($known as $k) {
            $marked[$k['verse'].':'.$k['word']] = $k['kind'];
        }

        $caught = array_intersect_key($marked, $flagged);

        return [
            'caught' => count($caught),
            'same_kind' => count(array_filter(array_keys($caught), fn (string $at) => $flagged[$at] === $marked[$at])),
            'missed' => array_values(array_map('strval', array_keys(array_diff_key($marked, $flagged)))),
            'false_alarms' => array_values(array_map('strval', array_keys(array_diff_key($flagged, $marked)))),
        ];
    }
}
