<?php

namespace Modules\Scheduling\Domain\Slots;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A weekly time on one clock: ISO weekday, wall-clock start, length.
 * The clock is the teacher's (docs/architecture/02, section 1), so the
 * lesson stays put for the teacher across DST changes.
 */
final readonly class WeeklyTime
{
    public const WEEK_MIN = 7 * 24 * 60;

    public function __construct(
        public int $weekday,
        public int $startMin,
        public int $durationMin,
    ) {
        if ($weekday < 1 || $weekday > 7 || $startMin < 0 || $startMin >= 24 * 60 || $durationMin <= 0 || $durationMin > 24 * 60) {
            throw new InvalidArgumentException('Bad weekly time.');
        }
    }

    public static function at(int $weekday, string $clock, int $durationMin): self
    {
        [$h, $m] = array_map('intval', explode(':', $clock));

        return new self($weekday, $h * 60 + $m, $durationMin);
    }

    public function clock(): string
    {
        return sprintf('%02d:%02d', intdiv($this->startMin, 60), $this->startMin % 60);
    }

    /**
     * Minutes from Monday 00:00 as a Postgres int4multirange literal; a time
     * running over Sunday midnight wraps to Monday morning.
     */
    public function minuteOfWeek(): string
    {
        $start = ($this->weekday - 1) * 24 * 60 + $this->startMin;
        $end = $start + $this->durationMin;

        return $end <= self::WEEK_MIN
            ? "{[{$start},{$end})}"
            : '{[0,'.($end - self::WEEK_MIN)."),[{$start},".self::WEEK_MIN.')}';
    }

    /**
     * Local dates of this weekday from $from to $to (inclusive), as Y-m-d.
     *
     * @return list<string>
     */
    public function datesBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $day = $from->startOfDay();
        $day = $day->addDays(($this->weekday - $day->dayOfWeekIso + 7) % 7);

        $dates = [];
        for (; $day->lessThanOrEqualTo($to); $day = $day->addWeek()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }
}
