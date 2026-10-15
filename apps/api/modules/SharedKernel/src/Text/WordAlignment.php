<?php

namespace Modules\SharedKernel\Text;

use InvalidArgumentException;

/**
 * Lines up the words someone said with the words they were meant to say:
 * each word of the text comes out ok, missing or wrong (with what was said
 * in its place), and every word said that is not in the text comes out
 * extra, after the word of the text it followed. Both sides are compared as
 * ArabicText's copies; the text itself is never changed.
 *
 * A global alignment (Needleman–Wunsch) with one cost for a word left out,
 * one for a word added and one for a word changed, so a word said twice is
 * one extra word and a recitation stopped early leaves the rest missing.
 * The opening (isti'adha, basmala) and the closing ("صدق الله العظيم") said
 * around a passage are not counted when the passage itself does not have
 * them. A word heard one letter apart from a long word of the text counts
 * as that word: speech to text spells some words its own way.
 */
final class WordAlignment
{
    /** A passage's words at most: a longer one is checked as several homeworks. */
    public const MAX_EXPECTED = 1200;

    /** Words heard at most: more than this is not a recitation of a passage this size. */
    public const MAX_HEARD = 1600;

    /** Words this long or longer match one letter apart. */
    private const NEAR_LENGTH = 5;

    private const OPENINGS = ['اعوذ بالله من الشيطان الرجيم', 'بسم الله الرحمن الرحيم'];

    private const CLOSINGS = ['صدق الله العظيم'];

    private const DIAGONAL = 'd';

    private const LEFT_OUT = 'u';

    private const ADDED = 'l';

    /**
     * @param  list<string>  $expected  the text's words, as kept
     * @param  list<string>  $heard  the words heard
     * @return array{words: list<array{state: 'ok'|'missing'|'wrong', heard: ?string}>, extra: list<array{after: int, text: string}>}
     *                                                                                                                                (one entry per expected word; extra after the expected word's number, 0 before the first)
     */
    public static function align(array $expected, array $heard): array
    {
        if (count($expected) > self::MAX_EXPECTED) {
            throw new InvalidArgumentException('Too many words to line up.');
        }
        $want = array_map(fn (string $w) => ArabicText::normalize($w), $expected);
        // Heard as one run, so a "و" heard apart joins its word as the text writes it.
        $said = self::trimmed($want, ArabicText::words(implode(' ', $heard)));
        $said = array_slice($said, 0, self::MAX_HEARD);

        // Words of the text that are only marks have nothing to say: they are ok as they are.
        $spoken = array_keys(array_filter($want, fn (string $w) => $w !== ''));
        $a = array_map(fn (int $i) => $want[$i], $spoken);
        [$pairs, $added] = self::lineUp($a, $said);

        $words = array_fill(0, count($expected), ['state' => 'ok', 'heard' => null]);
        foreach ($spoken as $k => $i) {
            $words[$i] = $pairs[$k];
        }
        $extra = [];
        foreach ($added as [$after, $text]) {
            $extra[] = ['after' => $after === 0 ? 0 : $spoken[$after - 1] + 1, 'text' => $text];
        }

        return ['words' => $words, 'extra' => $extra];
    }

    /**
     * @param  list<string>  $a  the text's words (normalized, none empty)
     * @param  list<string>  $b  the words heard (normalized)
     * @return array{0: list<array{state: 'ok'|'missing'|'wrong', heard: ?string}>, 1: list<array{0: int, 1: string}>}
     */
    private static function lineUp(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        $same = self::matcher($a, $b);

        // Costs a row at a time; the way back one byte a cell.
        $previous = range(0, $m);
        $way = str_repeat(self::ADDED, $m + 1);
        for ($i = 1; $i <= $n; $i++) {
            $row = [$i];
            $back = self::LEFT_OUT;
            for ($j = 1; $j <= $m; $j++) {
                $diagonal = $previous[$j - 1] + ($same($i - 1, $j - 1) ? 0 : 1);
                $up = $previous[$j] + 1;
                $left = $row[$j - 1] + 1;
                // On a tie a word heard is extra before it is matched, so of a word said twice the second is the extra one.
                if ($left <= $diagonal && $left <= $up) {
                    $row[] = $left;
                    $back .= self::ADDED;
                } elseif ($diagonal <= $up) {
                    $row[] = $diagonal;
                    $back .= self::DIAGONAL;
                } else {
                    $row[] = $up;
                    $back .= self::LEFT_OUT;
                }
            }
            $previous = $row;
            $way .= $back;
        }

        $pairs = [];
        $added = [];
        $i = $n;
        $j = $m;
        while ($i > 0 || $j > 0) {
            $step = $i === 0 ? self::ADDED : ($j === 0 ? self::LEFT_OUT : $way[$i * ($m + 1) + $j]);
            if ($step === self::DIAGONAL) {
                $pairs[$i - 1] = $same($i - 1, $j - 1) ? ['state' => 'ok', 'heard' => null] : ['state' => 'wrong', 'heard' => $b[$j - 1]];
                $i--;
                $j--;
            } elseif ($step === self::LEFT_OUT) {
                $pairs[$i - 1] = ['state' => 'missing', 'heard' => null];
                $i--;
            } else {
                $added[] = [$i, $b[$j - 1]];
                $j--;
            }
        }
        ksort($pairs);

        return [array_values($pairs), array_reverse($added)];
    }

    /**
     * Whether text word $i and heard word $j are the same word, with each pair worked out once.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return callable(int, int): bool
     */
    private static function matcher(array $a, array $b): callable
    {
        $ids = [];
        $ai = array_map(function (string $w) use (&$ids) {
            return $ids[$w] ??= count($ids);
        }, $a);
        $bi = array_map(function (string $w) use (&$ids) {
            return $ids[$w] ??= count($ids);
        }, $b);
        $near = [];

        return function (int $i, int $j) use ($a, $b, $ai, $bi, &$near): bool {
            if ($ai[$i] === $bi[$j]) {
                return true;
            }
            $key = $ai[$i].':'.$bi[$j];

            return $near[$key] ??= self::near($a[$i], $b[$j]);
        };
    }

    /**
     * @param  list<string>  $said
     * @param  list<string>  $words
     */
    private static function sameWords(array $said, array $words): bool
    {
        if (count($said) !== count($words)) {
            return false;
        }
        foreach ($words as $k => $word) {
            if ($said[$k] !== $word && ! self::near($said[$k], $word)) {
                return false;
            }
        }

        return true;
    }

    /** One letter apart, for words long enough that this is spelling, not another word. */
    private static function near(string $x, string $y): bool
    {
        $p = mb_str_split($x);
        $q = mb_str_split($y);
        if (min(count($p), count($q)) < self::NEAR_LENGTH || abs(count($p) - count($q)) > 1) {
            return false;
        }
        $previous = range(0, count($q));
        foreach ($p as $i => $c) {
            $row = [$i + 1];
            foreach ($q as $j => $d) {
                $row[] = min($previous[$j + 1] + 1, $row[$j] + 1, $previous[$j] + ($c === $d ? 0 : 1));
            }
            $previous = $row;
        }

        return end($previous) <= 1;
    }

    /**
     * The words heard without an opening or closing the text itself does not have.
     *
     * @param  list<string>  $want
     * @param  list<string>  $said
     * @return list<string>
     */
    private static function trimmed(array $want, array $said): array
    {
        $text = implode(' ', array_filter($want, fn (string $w) => $w !== ''));
        foreach (self::OPENINGS as $phrase) {
            $words = explode(' ', $phrase);
            if (! str_starts_with($text, $phrase) && self::sameWords(array_slice($said, 0, count($words)), $words)) {
                $said = array_slice($said, count($words));
            }
        }
        foreach (self::CLOSINGS as $phrase) {
            $words = explode(' ', $phrase);
            if (! str_ends_with($text, $phrase) && count($said) >= count($words) && self::sameWords(array_slice($said, -count($words)), $words)) {
                $said = array_slice($said, 0, -count($words));
            }
        }

        return array_values($said);
    }
}
