<?php

namespace Modules\People\Contracts;

/**
 * Which look a student's own area takes: a map with stars for children, levels
 * and streaks for middle school, a plan with goals for teenagers, and the
 * plain one for adults (and for guardians). From the age unless the academy
 * chose one.
 */
enum LearnerStage: string
{
    case Kids = 'kids';          // under 12: primary school
    case Middle = 'middle';      // 12–14: middle school (إعدادي)
    case Teen = 'teen';          // 15–17: secondary school
    case Classic = 'classic';    // adults (18 and over), or nobody knows

    public static function forAge(?int $age): self
    {
        return match (true) {
            $age === null, $age >= AgeGroup::ADULT_AGE => self::Classic,
            $age >= 15 => self::Teen,
            $age >= 12 => self::Middle,
            default => self::Kids,
        };
    }
}
