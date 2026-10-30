<?php

namespace Modules\Scheduling\Domain\Availability;

use Carbon\CarbonImmutable;

/**
 * What a student needs, in the student's own timezone
 * (docs/architecture/02, section 2, "أشكال البيانات").
 */
final readonly class StudentPreference
{
    /**
     * @param  list<array{weekday: int, start: int, end: int}>  $windows  ISO weekday, minutes after midnight, student clock
     */
    public function __construct(
        public string $timezone,
        public array $windows,
        public int $perWeek,
        public int $durationMin,
        public CarbonImmutable $startDate,
        public int $weeks = 8,
        public int $minCoverage = 8,
    ) {}

    /** Does [minute, minute + duration) on this student weekday fall inside one window? */
    public function accepts(int $weekday, int $minute, int $durationMin): bool
    {
        foreach ($this->windows as $w) {
            if ($w['weekday'] === $weekday && $minute >= $w['start'] && $minute + $durationMin <= $w['end']) {
                return true;
            }
        }

        return false;
    }

    /** One exact start on a student weekday: the window is the lesson itself (it may run past midnight). */
    public function exactly(int $weekday, int $minute, ?CarbonImmutable $startDate = null, ?int $weeks = null, ?int $minCoverage = null): self
    {
        return new self($this->timezone, [['weekday' => $weekday, 'start' => $minute, 'end' => $minute + $this->durationMin]],
            $this->perWeek, $this->durationMin, $startDate ?? $this->startDate, $weeks ?? $this->weeks, $minCoverage ?? $this->minCoverage);
    }

    /**
     * Hour cells (weekday => list of hours 0–23) as the grid sends them, merged
     * into windows. A run up to midnight goes on into the next day's run from
     * 00:00 (end past 24 * 60), so a lesson may start late and end after
     * midnight; it belongs to the day it starts.
     *
     * @param  array<int|string, list<int>>  $cells
     * @return list<array{weekday: int, start: int, end: int}>
     */
    public static function windowsFromHours(array $cells): array
    {
        $runs = [];
        foreach ($cells as $weekday => $hours) {
            $hours = array_values(array_unique(array_filter(array_map('intval', $hours), fn (int $h) => $h >= 0 && $h <= 23)));
            sort($hours);
            $start = null;
            foreach ($hours as $i => $hour) {
                $start ??= $hour;
                if (! isset($hours[$i + 1]) || $hours[$i + 1] !== $hour + 1) {
                    $runs[(int) $weekday][] = ['start' => $start * 60, 'end' => ($hour + 1) * 60];
                    $start = null;
                }
            }
        }

        $windows = [];
        foreach ($runs as $weekday => $list) {
            $next = $runs[$weekday % 7 + 1][0] ?? null;
            foreach ($list as $run) {
                if ($run['end'] === 24 * 60 && $next !== null && $next['start'] === 0) {
                    $run['end'] += $next['end'];
                }
                $windows[] = ['weekday' => $weekday, ...$run];
            }
        }

        return $windows;
    }
}
