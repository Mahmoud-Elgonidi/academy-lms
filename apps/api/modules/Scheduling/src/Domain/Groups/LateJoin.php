<?php

namespace Modules\Scheduling\Domain\Groups;

/**
 * A student joining a group after it began (docs/architecture/02, "الحصص
 * الجماعية"): the lessons they missed are the ones the group held in its
 * current block (its lessons since it started, block by block of the plan's
 * lessons), shown to the office, who may lower the number, never raise it.
 * What makes up for them is the office's choice at join time (the academy's
 * setting first): a discount on the first invoice (the lesson's price each,
 * at most the block), private makeup lessons with the group's teacher, a
 * seat in another group's lesson, or nothing. Makeup credit is the missed
 * lessons' minutes, valid MAKEUP_DAYS.
 */
final class LateJoin
{
    public const DISCOUNT = 'discount';

    public const PRIVATE = 'private';

    public const GROUP = 'group';

    public const NONE = 'none';

    public const CHOICES = [self::DISCOUNT, self::PRIVATE, self::GROUP, self::NONE];

    public const MAKEUP_DAYS = 30;

    /** The lessons of the current block the group already held. */
    public static function missed(int $held, int $sessionsPerBlock): int
    {
        return $held <= 0 || $sessionsPerBlock <= 0 ? 0 : $held % $sessionsPerBlock;
    }

    /** The number the office settles on: what was missed, or fewer. */
    public static function lessons(int $missed, ?int $asked): int
    {
        return max(0, min($missed, $asked ?? $missed));
    }

    /** The office's choice, else the academy's; null when it is not one of the four. Nothing missed, nothing to make up. */
    public static function choice(?string $asked, string $default, int $lessons): ?string
    {
        $choice = $asked ?? $default;
        if (! in_array($choice, self::CHOICES, true)) {
            return null;
        }

        return $lessons === 0 ? self::NONE : $choice;
    }

    /** Makeup minutes for the missed lessons (the private and group choices). */
    public static function makeupMinutes(string $choice, int $lessons, int $sessionMinutes): int
    {
        return in_array($choice, [self::PRIVATE, self::GROUP], true) ? $lessons * $sessionMinutes : 0;
    }

    /** The lessons the first invoice takes off (the discount choice). */
    public static function discountLessons(string $choice, int $lessons): int
    {
        return $choice === self::DISCOUNT ? $lessons : 0;
    }
}
