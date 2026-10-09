<?php

namespace Modules\People\Contracts;

/**
 * The ages a teacher teaches, as the stages of a student's area cut them:
 * children (under 12), middle school (12–14), secondary (15–18), adults.
 */
enum AgeGroup: string
{
    case Kids = 'kids';
    case Middle = 'middle';
    case Teen = 'teen';
    case Adults = 'adults';

    /** The group of a student this old; null when nobody knows. */
    public static function forAge(?int $age): ?self
    {
        return match (true) {
            $age === null => null,
            $age >= 19 => self::Adults,
            $age >= 15 => self::Teen,
            $age >= 12 => self::Middle,
            default => self::Kids,
        };
    }

    public function label(): string
    {
        return __('people::people.age_groups.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $g) => $g->label(), self::cases()));
    }
}
