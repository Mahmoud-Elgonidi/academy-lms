<?php

namespace Modules\Quran\Domain\Grading;

use InvalidArgumentException;
use Modules\Quran\Domain\Hifz\Scheduler;

/**
 * The grades a recitation gets, best first: excellent, very good, good,
 * acceptable, and repeat (it comes back next lesson). A program's pass grade
 * is one of the first four.
 */
final class Grades
{
    public const ORDER = ['excellent', 'very_good', 'good', 'acceptable', 'repeat'];

    public const PASSING = ['excellent', 'very_good', 'good', 'acceptable'];

    public static function rank(string $grade): int
    {
        $rank = array_search($grade, self::ORDER, true);

        return $rank === false ? throw new InvalidArgumentException("No grade {$grade}") : $rank;
    }

    /** Whether a grade reaches the threshold (the threshold itself counts). */
    public static function meets(string $grade, string $threshold): bool
    {
        return self::rank($grade) <= self::rank($threshold);
    }

    /** The grade as the hifz map's review counts it (Domain\Hifz\Scheduler). */
    public static function hifzGrade(string $grade): int
    {
        return match ($grade) {
            'excellent' => Scheduler::EASY,
            'very_good', 'good' => Scheduler::GOOD,
            'acceptable' => Scheduler::HARD,
            default => Scheduler::AGAIN,
        };
    }
}
