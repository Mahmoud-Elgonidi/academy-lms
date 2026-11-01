<?php

namespace Modules\Quran\Domain\Grading;

/**
 * Where each grade ends, in the weight of mistakes a page: excellent up to
 * 0.5, very good up to 1.5, good up to 3, acceptable up to 5, and repeat
 * beyond. Each bound is above the one before it, at most 50.
 */
final class GradeScale
{
    public const DEFAULT = ['excellent' => 0.5, 'very_good' => 1.5, 'good' => 3.0, 'acceptable' => 5.0];

    public const MAX = 50.0;

    /**
     * @return list<string> reasons (quran::settings.errors.*)
     */
    public static function errors(mixed $scale): array
    {
        if (! is_array($scale)) {
            return ['scale'];
        }
        $before = -1.0;
        foreach (Grades::PASSING as $grade) {
            $bound = $scale[$grade] ?? null;
            if (! is_numeric($bound) || (float) $bound < 0 || (float) $bound > self::MAX || (float) $bound <= $before) {
                return ['scale'];
            }
            $before = (float) $bound;
        }

        return array_diff(array_keys($scale), Grades::PASSING) === [] ? [] : ['scale'];
    }

    /** @return array<string, float> */
    public static function normalize(array $scale): array
    {
        $kept = [];
        foreach (Grades::PASSING as $grade) {
            $kept[$grade] = (float) $scale[$grade];
        }

        return $kept;
    }
}
