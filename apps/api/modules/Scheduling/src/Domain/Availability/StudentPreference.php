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

    /**
     * Hour cells (weekday => list of hours) as the grid sends them, merged into windows.
     *
     * @param  array<int|string, list<int>>  $cells
     * @return list<array{weekday: int, start: int, end: int}>
     */
    public static function windowsFromHours(array $cells): array
    {
        $windows = [];
        foreach ($cells as $weekday => $hours) {
            $hours = array_values(array_unique(array_map('intval', $hours)));
            sort($hours);
            $start = null;
            foreach ($hours as $i => $hour) {
                $start ??= $hour;
                if (! isset($hours[$i + 1]) || $hours[$i + 1] !== $hour + 1) {
                    $windows[] = ['weekday' => (int) $weekday, 'start' => $start * 60, 'end' => ($hour + 1) * 60];
                    $start = null;
                }
            }
        }

        return $windows;
    }
}
