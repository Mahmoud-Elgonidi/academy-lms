<?php

namespace Modules\Billing\Domain\Cards;

use Carbon\CarbonImmutable;
use Modules\Billing\Domain\Collections\CollectionRules;

/**
 * When a renewal is charged to the saved card (docs/architecture/02, section
 * 27, J5b), pure. The payer is told first (renewal_coming), then:
 *
 * - the charge comes at the invoice's due moment or three days after the
 *   notice, whichever is later; "three days" are days on the payer's clock
 *   (the same hour three days on, so a clock change in between moves nothing
 *   they read);
 * - unless rule 16 would cancel the first unpaid lesson before then: the
 *   charge comes early enough that, if it fails, "pay to keep the lesson"
 *   (lesson_at_risk, 24 hours ahead) still has its day: an hour before that
 *   window, and never sooner than an hour after the notice. The notice says
 *   the very moment, so nothing comes unannounced.
 */
final class ChargeMoment
{
    /** The payer is told this many days before the charge (the mandate's words say it). */
    public const NOTICE_DAYS = 3;

    /** At the earliest this long after the notice. */
    public const MIN_AFTER_NOTICE_HOURS = 1;

    public static function of(CarbonImmutable $noticedAt, string $timezone, ?CarbonImmutable $dueAt, ?CarbonImmutable $lessonCancelsAt = null): CarbonImmutable
    {
        $threeDays = $noticedAt->setTimezone($timezone)->addDays(self::NOTICE_DAYS)->utc();
        $at = $dueAt !== null && $dueAt->greaterThan($threeDays) ? $dueAt->utc() : $threeDays;

        if ($lessonCancelsAt !== null) {
            $latest = $lessonCancelsAt->subHours(CollectionRules::AT_RISK_HOURS + 1)->utc();
            if ($latest->lessThan($at)) {
                $earliest = $noticedAt->addHours(self::MIN_AFTER_NOTICE_HOURS)->utc();
                $at = $latest->greaterThan($earliest) ? $latest : $earliest;
            }
        }

        return $at;
    }
}
