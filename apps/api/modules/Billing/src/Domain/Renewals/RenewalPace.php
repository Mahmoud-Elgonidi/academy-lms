<?php

namespace Modules\Billing\Domain\Renewals;

/**
 * Where a course stands against its renewal mark, at its plan's pace: the
 * one reckoning of IssueRenewal's family window, the revenue forecast, the
 * family's balance and the renewal preview (docs/architecture/02, section 27,
 * J5a). Paid-for minutes left (makeup aside, booked or not: renewableMin)
 * come down by the plan's lessons a week; the next block's invoice goes out
 * when they reach the mark (renews_at lessons). An estimate: a lesson
 * cancelled or moved shifts it.
 */
final readonly class RenewalPace
{
    public function __construct(
        public int $renewableMin,
        public int $markMin,
        public int $sessionMinutes,
        public int $sessionsPerWeek,
    ) {}

    /** Whole days until the balance reaches its mark (0: at or under it already). */
    public function daysUntilMark(): int
    {
        $above = $this->renewableMin - $this->markMin;
        $perWeek = max(1, $this->sessionsPerWeek * $this->sessionMinutes);

        return $above <= 0 ? 0 : (int) ceil($above * 7 / $perWeek);
    }

    /** Whether the balance reaches its mark within these days at this pace (IssueRenewal's family window). */
    public function reachesMarkWithin(int $days): bool
    {
        $ahead = (int) ceil($this->sessionsPerWeek * $days / 7) * $this->sessionMinutes;

        return $this->renewableMin <= $this->markMin + $ahead;
    }

    /** The paid-for minutes left as whole lessons. */
    public function lessonsLeft(): int
    {
        return intdiv(max(0, $this->renewableMin), max(1, $this->sessionMinutes));
    }

    /** The lessons left when the next invoice goes out (the plan's renews_at). */
    public function markLessons(): int
    {
        return intdiv(max(0, $this->markMin), max(1, $this->sessionMinutes));
    }

    /** Lessons to take before the next invoice goes out (0: it is due now). */
    public function lessonsUntilMark(): int
    {
        return max(0, (int) ceil(($this->renewableMin - $this->markMin) / max(1, $this->sessionMinutes)));
    }
}
