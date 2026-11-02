<?php

namespace Modules\Billing\Domain\Collections;

use Carbon\CarbonImmutable;

/**
 * The collection timeline (docs/architecture/02, section 27, J5a), pure: which
 * reminder an invoice waiting to be paid is owed now, on its payer's clock.
 * Each step goes once per invoice (collection_steps holds them); the invoice
 * message itself (issued) went when it was issued.
 *
 * - due_tomorrow: the day before its due day, never when the invoice came out that day or later (its message said it);
 * - due_today: on its due day, while the moment is still ahead, never when it came out that day;
 * - overdue: OVERDUE_DAYS after its due day (the academy's collections hear it too), at most LATE_DAYS later
 *   (an invoice long overdue when this began is the office's, not a sudden message);
 * - lesson_at_risk: from AT_RISK_HOURS before rule 16 would cancel its first unpaid lesson, until LAST_HOURS before.
 *
 * Day steps go from FROM_HOUR on the payer's clock (Communication still holds anything in their quiet hours), and at
 * most one a day per invoice; lesson_at_risk goes when it comes due (it names a moment), and a day with it has no other.
 */
final class CollectionRules
{
    public const ISSUED = 'issued';

    public const DUE_TOMORROW = 'due_tomorrow';

    public const DUE_TODAY = 'due_today';

    public const OVERDUE = 'overdue';

    public const LESSON_AT_RISK = 'lesson_at_risk';

    public const RENEWAL_PREVIEW = 'renewal_preview';

    public const PAY_LINK = 'pay_link';

    public const FROM_HOUR = 9;

    public const OVERDUE_DAYS = 2;

    public const LATE_DAYS = 3;

    public const AT_RISK_HOURS = 24;

    public const LAST_HOURS = 1;

    /** The renewal preview goes when the mark is this many days away or fewer (and not today: the invoice itself goes then). */
    public const PREVIEW_DAYS = 3;

    /** The live board's "send payment link" goes at most once in this many minutes per invoice. */
    public const PAY_LINK_MINUTES = 60;

    /**
     * The step owed now, or null.
     *
     * @param  array<string, CarbonImmutable>  $sent  steps already sent on this invoice, with when
     * @param  ?CarbonImmutable  $lessonCancelsAt  when rule 16 would cancel its first unpaid lesson; null when none would
     */
    public static function due(CarbonImmutable $now, string $timezone, CarbonImmutable $issuedAt, ?CarbonImmutable $dueAt, array $sent, ?CarbonImmutable $lessonCancelsAt = null): ?string
    {
        $local = $now->setTimezone($timezone);
        $today = $local->toDateString();

        if ($lessonCancelsAt !== null && ! isset($sent[self::LESSON_AT_RISK])
            && $now->greaterThanOrEqualTo($lessonCancelsAt->subHours(self::AT_RISK_HOURS))
            && $now->lessThan($lessonCancelsAt->subHours(self::LAST_HOURS))) {
            return self::LESSON_AT_RISK;
        }
        if ($dueAt === null || $local->hour < self::FROM_HOUR) {
            return null;
        }
        foreach ($sent as $step => $at) {
            if ($step !== self::ISSUED && $step !== self::PAY_LINK && $at->setTimezone($timezone)->toDateString() === $today) {
                return null;    // one a day
            }
        }

        // Calendar days on the payer's clock, counted as plain dates (a day of 23 or 25 hours is still one day).
        $dueDay = CarbonImmutable::parse($dueAt->setTimezone($timezone)->toDateString(), 'UTC');
        $issuedDay = $issuedAt->setTimezone($timezone)->toDateString();
        $late = (int) round($dueDay->diffInDays(CarbonImmutable::parse($today, 'UTC'), false));

        return match (true) {
            $late >= self::OVERDUE_DAYS => $late <= self::OVERDUE_DAYS + self::LATE_DAYS && ! isset($sent[self::OVERDUE]) ? self::OVERDUE : null,
            $late === 0 => $issuedDay < $today && $now->lessThan($dueAt) && ! isset($sent[self::DUE_TODAY]) ? self::DUE_TODAY : null,
            $late === -1 => $issuedDay < $today && ! isset($sent[self::DUE_TOMORROW]) ? self::DUE_TOMORROW : null,
            default => null,
        };
    }

    /** Whether a course's renewal preview is owed now: its mark is 1 to PREVIEW_DAYS days away, from FROM_HOUR on the payer's clock. */
    public static function previewDue(int $daysUntilMark, CarbonImmutable $now, string $timezone): bool
    {
        return $daysUntilMark >= 1 && $daysUntilMark <= self::PREVIEW_DAYS && $now->setTimezone($timezone)->hour >= self::FROM_HOUR;
    }
}
