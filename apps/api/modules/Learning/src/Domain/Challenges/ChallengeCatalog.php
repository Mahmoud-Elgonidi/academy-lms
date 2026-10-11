<?php

namespace Modules\Learning\Domain\Challenges;

use Modules\Learning\Domain\Journey\Cheers;
use Modules\Learning\Domain\Journey\Stickers;
use Modules\Learning\Domain\Journey\Week;

/**
 * The challenges each stage plays (docs/architecture/02, "التحديات"), all
 * counted from what really happened: lessons attended and joined on time,
 * homework handed in by its due time, lessons mastered, the teacher's focus
 * score, levels finished. Nothing is self-reported, so nothing can be faked.
 *
 * - kids: three weekly missions and a treasure chest once they are all done
 *   (a sticker for the album and gems).
 * - middle: weekly quests for XP, and a "boss" for finishing the level.
 * - teen, and adult students (classic): weekly goals and a month without
 *   missing a lesson.
 *
 * Kept for earlier app versions (learning/challenges); the journey's week,
 * stickers and cheers are Domain\Journey's, which this refers to.
 */
final class ChallengeCatalog
{
    /** Weeks start on Saturday, on the student's own clock (the journey's week). */
    public const WEEK_STARTS = Week::STARTS;

    /** Minutes after the start that still count as coming in on time. */
    public const ON_TIME_GRACE_MIN = 3;

    /** Days with a finished practice round that make the week's practice challenge. */
    public const PRACTICE_DAYS = 3;

    /** A guardian's cheers to one learner in a day (the journey's). */
    public const CHEERS_PER_DAY = Cheers::PER_DAY;

    /**
     * Per stage, in the order shown: [challenge, period (week|month|world), reward in points].
     *
     * @var array<string, list<array{0: string, 1: string, 2: int}>>
     */
    public const STAGES = [
        'kids' => [['attend', 'week', 50], ['homework', 'week', 30], ['on_time', 'week', 30], ['practice', 'week', 40], ['chest', 'week', 50]],
        'middle' => [['attend', 'week', 100], ['homework', 'week', 80], ['master', 'week', 120], ['focus', 'week', 60], ['practice', 'week', 60], ['boss', 'world', 300]],
        'teen' => [['attend', 'week', 80], ['homework', 'week', 80], ['master', 'week', 150], ['practice', 'week', 60], ['month', 'month', 300]],
        'classic' => [['attend', 'week', 80], ['homework', 'week', 80], ['master', 'week', 150], ['month', 'month', 300]],
    ];

    /** The kids' album: one sticker per chest, in this order (then round again): the journey's. */
    public const STICKERS = Stickers::ALBUM;

    /** @return list<array{0: string, 1: string, 2: int}> */
    public static function for(string $stage): array
    {
        return self::STAGES[$stage] ?? self::STAGES['classic'];
    }

    public static function reward(string $stage, string $challenge): ?int
    {
        foreach (self::for($stage) as [$key, , $points]) {
            if ($key === $challenge) {
                return $points;
            }
        }

        return null;
    }

    /** The sticker the n-th chest (0-based) brings. */
    public static function sticker(int $n): string
    {
        return Stickers::nth($n);
    }
}
