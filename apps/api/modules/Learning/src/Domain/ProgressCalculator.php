<?php

namespace Modules\Learning\Domain;

use Modules\Curriculum\Contracts\DTOs\CurriculumTree;

/**
 * The numbers on the roadmap (docs/architecture/02, section 6):
 *
 * - position = the first lesson, in teaching order, not yet mastered;
 * - percent = weight mastered ÷ total weight, rounded down (100 only at the end);
 * - pace = average weight mastered per lesson over the last 6 lesson reports;
 * - ETA = weight left ÷ pace ÷ lessons a week.
 */
final class ProgressCalculator
{
    public const PACE_WINDOW = 6;

    /**
     * @param  array<string, NodeProgress>  $progress  by lesson id
     * @param  list<int>  $masteredPerLesson  weight newly mastered by each lesson report, oldest first
     */
    public static function position(CurriculumTree $tree, array $progress, array $masteredPerLesson, int $lessonsPerWeek): Position
    {
        $total = 0;
        $mastered = 0;
        $current = null;
        foreach ($tree->lessons() as $lesson) {
            $total += $lesson->weight;
            if (isset($progress[$lesson->id]) && $progress[$lesson->id]->isMastered()) {
                $mastered += $lesson->weight;
            } else {
                $current ??= $lesson->id;
            }
        }

        $recent = array_slice($masteredPerLesson, -self::PACE_WINDOW);
        $pace = $recent === [] ? null : round(array_sum($recent) / count($recent), 3);
        $left = $total - $mastered;
        $eta = match (true) {
            $total === 0 => null,
            $left === 0 => 0.0,
            $pace === null || $pace <= 0.0 || $lessonsPerWeek <= 0 => null,
            default => round($left / $pace / $lessonsPerWeek, 1),
        };

        return new Position($current, $mastered, $total, self::percent($mastered, $total), $pace, $eta);
    }

    /** @param  array<string, NodeProgress>  $progress */
    public static function percentUnder(CurriculumTree $tree, array $progress, string $nodeId): int
    {
        $total = 0;
        $mastered = 0;
        foreach ($tree->lessons($nodeId) as $lesson) {
            $total += $lesson->weight;
            $mastered += (isset($progress[$lesson->id]) && $progress[$lesson->id]->isMastered()) ? $lesson->weight : 0;
        }

        return self::percent($mastered, $total);
    }

    private static function percent(int $mastered, int $total): int
    {
        return $total === 0 ? 0 : intdiv(100 * $mastered, $total);
    }
}
