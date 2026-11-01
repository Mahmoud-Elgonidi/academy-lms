<?php

namespace Modules\Crm\Domain;

use Carbon\CarbonImmutable;

/**
 * The academy's offer after a free trial (docs/architecture/02, section 27,
 * J2), pure: its bounds (a percentage from 5 to 50, open from 24 hours to two
 * weeks), when it ends (counted from the report going out, so the family
 * has every hour of it), and when its one reminder is due: in its last 24
 * hours, never sooner than 12 hours after the report (an offer of a day is
 * not chased the moment it is made) and never in its last 2 hours (too late
 * to act on calmly).
 */
final class TrialOfferRules
{
    public const MIN_PERCENT = 5;

    public const MAX_PERCENT = 50;

    public const MIN_HOURS = 24;

    public const MAX_HOURS = 336;

    public const REMIND_BEFORE_HOURS = 24;

    public const REMIND_AFTER_REPORT_HOURS = 12;

    public const REMIND_UNTIL_LEFT_HOURS = 2;

    /** @return list<string> the fields that are out of bounds: percent, hours */
    public static function invalid(int $percent, int $hours): array
    {
        return array_values(array_filter([
            $percent < self::MIN_PERCENT || $percent > self::MAX_PERCENT ? 'percent' : null,
            $hours < self::MIN_HOURS || $hours > self::MAX_HOURS ? 'hours' : null,
        ]));
    }

    public static function endsAt(CarbonImmutable $from, int $hours): CarbonImmutable
    {
        return $from->utc()->addHours($hours);
    }

    public static function remindDue(CarbonImmutable $sentAt, CarbonImmutable $endsAt, CarbonImmutable $now): bool
    {
        return $now->greaterThanOrEqualTo($endsAt->subHours(self::REMIND_BEFORE_HOURS))
            && $now->greaterThanOrEqualTo($sentAt->addHours(self::REMIND_AFTER_REPORT_HOURS))
            && $now->lessThan($endsAt->subHours(self::REMIND_UNTIL_LEFT_HOURS));
    }
}
