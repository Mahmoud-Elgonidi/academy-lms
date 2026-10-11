<?php

namespace Modules\Learning\Domain\Journey;

use Carbon\CarbonImmutable;

/**
 * Stars, the journey's one reckoning (docs/architecture/02, section 18): each
 * lesson brings its own (LessonStars), each practice round its own (one to
 * three), homework handed in by its due time two and late one, a lesson the
 * teacher marks mastered five, and the week's chest ten. Every
 * PER_EGG stars an egg is ready to hatch; the total gives the rank.
 */
final class Stars
{
    public const HOMEWORK_ON_TIME = 2;

    public const HOMEWORK_LATE = 1;

    public const MASTERED = 5;

    public const CHEST = 10;

    public const PER_EGG = 12;

    /** Ranks by stars. */
    public const RANKS = [0 => 'beginner', 30 => 'explorer', 80 => 'star', 150 => 'hero', 300 => 'legend'];

    /** A homework's stars: on time two, late one, not handed in none. */
    public static function homework(CarbonImmutable $dueAt, ?CarbonImmutable $handedInAt): int
    {
        return match (true) {
            $handedInAt === null => 0,
            $handedInAt->lessThanOrEqualTo($dueAt) => self::HOMEWORK_ON_TIME,
            default => self::HOMEWORK_LATE,
        };
    }

    /** @return array{key: string, stars: int, next: ?int, next_key: ?string, progress: int} */
    public static function rank(int $stars): array
    {
        [$key, $from, $next, $nextKey] = ['beginner', 0, null, null];
        foreach (self::RANKS as $threshold => $name) {
            if ($stars >= $threshold) {
                [$key, $from] = [$name, $threshold];
            } elseif ($next === null) {
                [$next, $nextKey] = [$threshold, $name];
            }
        }

        return ['key' => $key, 'stars' => $stars, 'next' => $next, 'next_key' => $nextKey,
            'progress' => $next === null ? 100 : (int) floor(($stars - $from) * 100 / ($next - $from))];
    }
}
