<?php

namespace Modules\Scheduling\Domain\Groups;

/**
 * What keeps a group whole (docs/architecture/02, "الحصص الجماعية"),
 * without the database: seats, capacity against the teacher's most and the
 * members already in, the plan a member joins on, and who may learn
 * together. Each check answers null, or the key of the reason
 * (scheduling::groups.errors.*) for the person to read.
 */
final class GroupRules
{
    public const MIN_SIZE = 2;

    public const MAX_SIZE = 20;

    /** A group's capacity: within 2–20, at most the teacher's (who must take groups), never below the seats taken. */
    public static function capacity(int $capacity, ?int $teacherMax, int $taken = 0): ?string
    {
        return match (true) {
            $teacherMax === null => 'no_groups',
            $capacity < self::MIN_SIZE || $capacity > self::MAX_SIZE => 'capacity_range',
            $capacity > $teacherMax => 'over_teacher',
            $capacity < $taken => 'below_members',
            default => null,
        };
    }

    /** The students it waits for before it starts: none (its first), or 1 up to its capacity. */
    public static function minToStart(?int $min, int $capacity): ?string
    {
        return $min !== null && ($min < 1 || $min > $capacity) ? 'min_to_start' : null;
    }

    /** A free seat for one more. */
    public static function seat(int $taken, int $capacity): ?string
    {
        return $taken >= $capacity ? 'full' : null;
    }

    /** A member joins on a group plan of the group's shape: its lesson length and lessons a week. */
    public static function plan(bool $planIsGroup, int $planMinutes, int $planPerWeek, int $groupMinutes, int $groupPerWeek): ?string
    {
        return match (true) {
            ! $planIsGroup => 'plan_not_group',
            $planMinutes !== $groupMinutes || $planPerWeek !== $groupPerWeek => 'plan_shape',
            default => null,
        };
    }

    /**
     * Who learns together: one age group (when both are known), and minors
     * never with adults; unless the academy allows mixed groups.
     *
     * @param  list<bool>  $membersMinor  whether each member already in is under 18
     */
    public static function ages(?string $groupAge, ?string $studentAge, bool $studentMinor, array $membersMinor, bool $mixedAllowed): ?string
    {
        if ($mixedAllowed) {
            return null;
        }
        if ($groupAge !== null && $studentAge !== null && $groupAge !== $studentAge) {
            return 'age_band';
        }

        return in_array(! $studentMinor, $membersMinor, true) ? 'minors_adults' : null;
    }

    /** The group's level, when it has one, is the student's (when known). */
    public static function level(?string $groupLevel, ?string $studentLevel): ?string
    {
        return $groupLevel !== null && $studentLevel !== null && $groupLevel !== $studentLevel ? 'level' : null;
    }

    /** It has the students it waits for: its minimum, or its first when it has none. */
    public static function startsWith(int $taken, ?int $minToStart): bool
    {
        return $taken >= max(1, $minToStart ?? 1);
    }
}
