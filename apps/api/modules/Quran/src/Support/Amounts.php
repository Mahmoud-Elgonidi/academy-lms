<?php

namespace Modules\Quran\Support;

/**
 * A lesson's amount as people read it, in the reader's language
 * (quran::screens.amounts): "4 verses", "half a page", "2 quarters of a
 * hizb", "one lesson"; and a program's pace in a line (new, near, far).
 */
final class Amounts
{
    public static function of(float $amount, string $unit): string
    {
        $k = 'quran::screens.amounts.';
        if ($unit === 'page') {
            foreach ([[0.25, 'quarter'], [0.5, 'half'], [0.75, 'three_quarters'], [1.0, 'one'], [1.5, 'one_half'], [2.0, 'two']] as [$value, $key]) {
                if (abs($amount - $value) < 1e-9) {
                    return __($k.'fraction.'.$key);
                }
            }
        }
        $n = self::number($amount);

        return trans_choice($k.$unit, self::count($amount), ['n' => $n]);
    }

    /** "new 4 verses · near 2 · far 3 pages" for a pace. */
    public static function pace(float $new, string $unit, int $near, int $far): string
    {
        $k = 'quran::screens.pace.';
        $parts = [];
        if ($new > 0) {
            $parts[] = __($k.'new', ['amount' => self::of($new, $unit)]);
        }
        if ($near > 0) {
            $parts[] = trans_choice($k.'near', $near, ['n' => $near]);
        }
        if ($far > 0) {
            $parts[] = trans_choice($k.'far', $far, ['n' => $far]);
        }

        return $parts === [] ? __($k.'none') : implode(' · ', $parts);
    }

    public static function number(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }

    /** The count trans_choice reads: a fraction reads as its whole part, at least two ("2.5 pages"). */
    private static function count(float $amount): int
    {
        return abs($amount - round($amount)) < 1e-9 ? (int) round($amount) : max(2, (int) floor($amount));
    }
}
