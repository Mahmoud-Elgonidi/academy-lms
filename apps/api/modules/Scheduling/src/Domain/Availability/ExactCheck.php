<?php

namespace Modules\Scheduling\Domain\Availability;

/**
 * One exact start typed for one teacher ("another time…", "search at an
 * exact time"), checked week by week like any proposed time: the weekly time
 * it makes (null when no week is free) and, for each week it is not, why,
 * with the teacher-local dates.
 */
final readonly class ExactCheck
{
    public const PAST = 'past';

    public const HOLIDAY = 'holiday';

    public const TIME_OFF = 'time_off';

    public const SHIFTS = 'shifts';

    public const BUSY = 'busy';

    public const HELD = 'held';

    public const CAP = 'cap';

    /** Summer time moves one of the clocks: that week the lesson is at another hour for the student. */
    public const CLOCK = 'clock';

    /**
     * @param  array<string, list<string>>  $reasons  reason => teacher-local dates (Y-m-d)
     */
    public function __construct(
        public int $studentWeekday,
        public string $studentTime,
        public ?Candidate $candidate,
        public int $weeks,
        public int $minCoverage,
        public array $reasons,
    ) {}

    public function fits(): bool
    {
        return $this->candidate !== null && $this->candidate->coverage >= $this->minCoverage;
    }

    public function coverage(): int
    {
        return $this->candidate->coverage ?? 0;
    }

    /** The reasons, the most weeks first. */
    public function sortedReasons(): array
    {
        $reasons = $this->reasons;
        uasort($reasons, fn (array $a, array $b) => count($b) <=> count($a));

        return $reasons;
    }
}
