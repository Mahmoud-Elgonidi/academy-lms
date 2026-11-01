<?php

namespace Modules\Quran\Domain\Grading;

/**
 * A recitation's grade from its mistakes (docs/architecture/02, section 26):
 * each kind counted, times its weight, over the pages recited (a part
 * shorter than half a page counts as half a page, so a few verses are not
 * judged harder than a page), then placed on the academy's scale. A kind the
 * academy no longer has weighs nothing. The teacher may give another grade,
 * with a note saying why (decide()).
 */
final class Grading
{
    /** The least a part counts as, in pages. */
    public const MIN_PAGES = 0.5;

    /**
     * @param  array<string, int>  $counts  kind => how many
     * @param  array<string, float>  $weights  kind => weight
     * @param  array<string, float>  $scale  GradeScale
     * @return array{grade: string, penalty: float}
     */
    public static function grade(array $counts, array $weights, array $scale, float $pages): array
    {
        $weight = 0.0;
        foreach ($counts as $kind => $count) {
            $weight += max(0, (int) $count) * ($weights[$kind] ?? 0.0);
        }
        $penalty = round($weight / max($pages, self::MIN_PAGES), 4);
        foreach (Grades::PASSING as $grade) {
            if ($penalty <= ($scale[$grade] ?? GradeScale::DEFAULT[$grade]) + 1e-9) {
                return ['grade' => $grade, 'penalty' => $penalty];
            }
        }

        return ['grade' => 'repeat', 'penalty' => $penalty];
    }

    /**
     * The grade that counts: the teacher's when given (a different one needs a note), else the worked-out one.
     *
     * @return array{grade: string, overridden: bool, error: ?string} error: note (a note is needed) or grade (no such grade)
     */
    public static function decide(string $computed, ?string $given, ?string $note): array
    {
        if ($given === null || $given === $computed) {
            return ['grade' => $computed, 'overridden' => false, 'error' => null];
        }
        if (! in_array($given, Grades::ORDER, true)) {
            return ['grade' => $computed, 'overridden' => false, 'error' => 'grade'];
        }
        if (trim((string) $note) === '') {
            return ['grade' => $computed, 'overridden' => false, 'error' => 'note'];
        }

        return ['grade' => $given, 'overridden' => true, 'error' => null];
    }
}
