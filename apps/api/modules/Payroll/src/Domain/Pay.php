<?php

namespace Modules\Payroll\Domain;

use InvalidArgumentException;

/**
 * What a lesson pays (docs/architecture/02, "مرتبات المدرسين"): the rate
 * card line that fits it best, times the share of the pay the policy
 * decided (100% taught, 50% a late cancellation by the student, 0% the
 * teacher's absence). By the hour, the booked minutes are paid; amounts are
 * rounded half up to the currency's minor unit, never through a float.
 */
final class Pay
{
    /**
     * The best line for a lesson of its format: a group lesson takes the
     * card's group lines, and falls back to its private ones when none fits.
     *
     * @param  iterable<RateLine>  $lines
     */
    public static function pick(iterable $lines, string $subjectId, string $sessionType, string $format = RateLine::PRIVATE): ?RateLine
    {
        $lines = is_array($lines) ? $lines : iterator_to_array($lines, false);
        $own = self::bestOf(array_filter($lines, fn (RateLine $l) => $l->format === $format), $subjectId, $sessionType);

        return $own ?? ($format === RateLine::GROUP ? self::bestOf(array_filter($lines, fn (RateLine $l) => $l->format === RateLine::PRIVATE), $subjectId, $sessionType) : null);
    }

    /** @param  array<RateLine>  $lines */
    private static function bestOf(array $lines, string $subjectId, string $sessionType): ?RateLine
    {
        $best = null;
        $bestFit = -1;
        foreach ($lines as $line) {
            $fit = $line->fit($subjectId, $sessionType);
            if ($fit !== null && $fit > $bestFit) {
                [$best, $bestFit] = [$line, $fit];
            }
        }

        return $best;
    }

    /**
     * The share of the pay the lesson's outcome earns, as the policy recorded
     * it on the lesson; a lesson decided before the share was recorded pays
     * in full when it was taught or the student did not come.
     *
     * @param  array<string, mixed>|null  $decision
     */
    public static function share(string $status, ?array $decision): int
    {
        if (! in_array($status, ['completed', 'student_absent', 'teacher_absent', 'cancelled'], true)) {
            return 0;
        }
        if (isset($decision['teacher_pay_pct']) && is_numeric($decision['teacher_pay_pct'])) {
            return max(0, min(100, (int) $decision['teacher_pay_pct']));
        }
        if (isset($decision['teacher_pay'])) {
            return $decision['teacher_pay'] === 'full' ? 100 : 0;
        }

        return in_array($status, ['completed', 'student_absent'], true) ? 100 : 0;
    }

    public static function amount(RateLine $line, int $bookedMinutes, int $payPct): int
    {
        if ($bookedMinutes < 0 || $payPct < 0 || $payPct > 100) {
            throw new InvalidArgumentException("Bad lesson [{$bookedMinutes} min, {$payPct}%].");
        }

        return $line->unit === 'hour'
            ? self::divideHalfUp($line->amountMinor * $bookedMinutes * $payPct, 60 * 100)
            : self::divideHalfUp($line->amountMinor * $payPct, 100);
    }

    private static function divideHalfUp(int $n, int $d): int
    {
        return intdiv(2 * $n + $d, 2 * $d);
    }
}
