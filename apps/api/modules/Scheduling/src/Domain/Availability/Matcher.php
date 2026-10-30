<?php

namespace Modules\Scheduling\Domain\Availability;

use Carbon\CarbonImmutable;
use Modules\SharedKernel\Time\LocalTimeResolver;

/**
 * Finds the weekly times a teacher can take a student
 * (docs/architecture/02, section 2, "الخوارزمية", steps 3–4):
 *
 *  - start times on a 15-minute grid of the teacher's clock, wherever the
 *    teacher is free (and any minute typed by hand, through exact());
 *  - each one checked week by week (summer time moves clocks, bookings come
 *    and go): the whole session free, and inside the student's windows on
 *    the student's clock;
 *  - a suggested pattern of N different days: enough weeks first, then the
 *    same time every day, then a day off between sessions.
 */
final class Matcher
{
    public const GRID_MIN = 15;

    public const CHIPS_PER_DAY = 4;

    public function __construct(private readonly LocalTimeResolver $resolver) {}

    /** @return list<Candidate> */
    public function candidates(TeacherAvailability $teacher, StudentPreference $pref): array
    {
        $horizonStart = CarbonImmutable::parse($pref->startDate->toDateString(), $pref->timezone)->utc();
        $horizonEnd = $horizonStart->addWeeks($pref->weeks);
        $duration = $pref->durationMin * 60;

        // Every teacher-clock start that is free at least once in the horizon.
        $keys = [];
        foreach ($teacher->free->intervals as [$from, $to]) {
            $from = max($from, $horizonStart->getTimestamp());
            $to = min($to, $horizonEnd->getTimestamp());
            $local = CarbonImmutable::createFromTimestamp($from, 'UTC')->setTimezone($teacher->timezone);
            $local = $local->addMinutes((self::GRID_MIN - ($local->minute % self::GRID_MIN)) % self::GRID_MIN)->second(0);
            while ($local->getTimestamp() + $duration <= $to) {
                $keys[$local->dayOfWeekIso.'|'.$local->format('H:i')] = true;
                $local = $local->addMinutes(self::GRID_MIN);
            }
        }

        $candidates = [];
        foreach (array_keys($keys) as $key) {
            [$weekday, $time] = explode('|', $key);
            $candidate = $this->evaluate($teacher, $pref, (int) $weekday, $time, $horizonStart);
            if ($candidate !== null && $candidate->coverage >= $pref->minCoverage) {
                $candidates[] = $candidate;
            }
        }

        usort($candidates, fn (Candidate $a, Candidate $b) => [$a->studentWeekday, $a->studentTime] <=> [$b->studentWeekday, $b->studentTime]);

        return $candidates;
    }

    /**
     * The few times per student weekday worth showing: most weeks first, then
     * whole and half hours, earliest first.
     *
     * @param  list<Candidate>  $candidates
     * @return array<int, list<Candidate>> student weekday => chips
     */
    public function chips(array $candidates): array
    {
        $byDay = [];
        foreach ($candidates as $c) {
            $byDay[$c->studentWeekday][] = $c;
        }

        foreach ($byDay as $day => $list) {
            usort($list, fn (Candidate $a, Candidate $b) => [-$a->coverage, self::roundness($a->studentTime), $a->studentTime]
                <=> [-$b->coverage, self::roundness($b->studentTime), $b->studentTime]);
            $picked = array_slice($list, 0, self::CHIPS_PER_DAY);
            usort($picked, fn (Candidate $a, Candidate $b) => $a->studentTime <=> $b->studentTime);
            $byDay[$day] = $picked;
        }
        ksort($byDay);

        return $byDay;
    }

    /**
     * @param  list<Candidate>  $candidates
     * @return list<Candidate>|null one per day, or null if there are not enough days
     */
    public function suggest(array $candidates, int $perWeek): ?array
    {
        $chips = $this->chips($candidates);
        if (count($chips) < $perWeek) {
            return null;
        }

        $best = null;
        $bestScore = null;
        foreach (self::combinations(array_keys($chips), $perWeek) as $days) {
            foreach (self::product(array_map(fn (int $d) => $chips[$d], $days)) as $pick) {
                $score = self::score($pick);
                if ($bestScore === null || $score > $bestScore) {
                    [$best, $bestScore] = [$pick, $score];
                }
            }
        }

        return $best;
    }

    /**
     * One exact start on the student's clock (any minute, "17:40") checked
     * for this teacher with the same rules as a proposed time: it becomes
     * the weekly time on the teacher's clock that its first week falls on,
     * then every week of the horizon must be free and still at that time for
     * the student. A start before $notBefore (now) counts as gone.
     */
    public function exact(TeacherAvailability $teacher, StudentPreference $pref, int $studentWeekday, string $studentTime, ?int $notBefore = null): ExactCheck
    {
        [$h, $m] = array_map('intval', explode(':', $studentTime));
        $exact = $pref->exactly($studentWeekday, $h * 60 + $m);
        $horizonStart = CarbonImmutable::parse($pref->startDate->toDateString(), $pref->timezone)->utc();

        $firstDate = CarbonImmutable::parse($pref->startDate->toDateString(), $pref->timezone);
        $firstDate = $firstDate->addDays(($studentWeekday - $firstDate->dayOfWeekIso + 7) % 7);
        $teacherLocal = $this->resolver->resolve($firstDate->toDateString(), sprintf('%02d:%02d', $h, $m), $pref->timezone)->utc->setTimezone($teacher->timezone);

        $walk = $this->walk($teacher, $exact, $teacherLocal->dayOfWeekIso, $teacherLocal->format('H:i'), $horizonStart, $notBefore, explain: true);

        return new ExactCheck($studentWeekday, sprintf('%02d:%02d', $h, $m), $walk['candidate']?->asTyped(), $walk['weeks'], $pref->minCoverage, $walk['reasons']);
    }

    private function evaluate(TeacherAvailability $teacher, StudentPreference $pref, int $weekday, string $time, CarbonImmutable $horizonStart): ?Candidate
    {
        return $this->walk($teacher, $pref, $weekday, $time, $horizonStart)['candidate'];
    }

    /**
     * Every week of a weekly time on the teacher's clock: whether the whole
     * lesson is free and inside the student's windows on their clock (the
     * day it starts), and why not where it is not.
     *
     * @return array{candidate: ?Candidate, weeks: int, reasons: array<string, list<string>>}
     */
    private function walk(TeacherAvailability $teacher, StudentPreference $pref, int $weekday, string $time, CarbonImmutable $horizonStart, ?int $notBefore = null, bool $explain = false): array
    {
        $firstDay = $horizonStart->setTimezone($teacher->timezone)->startOfDay();
        $firstDay = $firstDay->addDays(($weekday - $firstDay->dayOfWeekIso + 7) % 7);

        $coverage = 0;
        $occurrences = [];
        $conflicts = [];
        $reasons = [];
        $student = null;
        $week = 0;

        for ($i = 0; count($occurrences) < $pref->weeks && $i < $pref->weeks + 1; $i++) {
            $date = $firstDay->addWeeks($i);
            $start = $this->resolver->resolve($date->toDateString(), $time, $teacher->timezone)->utc;
            if ($start->lessThan($horizonStart)) {
                continue;
            }

            $ts = $start->getTimestamp();
            $end = $ts + $pref->durationMin * 60;
            $occurrences[] = $ts;
            $studentLocal = $start->setTimezone($pref->timezone);
            $inWindow = $pref->accepts($studentLocal->dayOfWeekIso, $studentLocal->hour * 60 + $studentLocal->minute, $pref->durationMin);
            $gone = $notBefore !== null && $ts < $notBefore;
            $free = ! $gone && $teacher->free->contains($ts, $end);

            if ($inWindow && $free) {
                $coverage++;
                $student ??= $studentLocal;
            } else {
                $conflicts[] = $date->toDateString();
                if (! $explain) {
                    $week++;

                    continue;
                }
                $reasons[match (true) {
                    $gone => ExactCheck::PAST,
                    ! $free => $teacher->whyBusy($ts, $end),
                    default => ExactCheck::CLOCK,
                }][] = $date->toDateString();
            }
            $week++;
        }

        return [
            'candidate' => $student === null ? null
                : new Candidate($weekday, $time, $student->dayOfWeekIso, $student->format('H:i'), $coverage, $week, $occurrences, $conflicts),
            'weeks' => $week,
            'reasons' => $reasons,
        ];
    }

    /** @param  list<Candidate>  $pick */
    private static function score(array $pick): array
    {
        $days = array_map(fn (Candidate $c) => $c->studentWeekday, $pick);
        sort($days);
        $gaps = [];
        foreach ($days as $i => $day) {
            $next = $days[($i + 1) % count($days)] + ($i + 1 === count($days) ? 7 : 0);
            $gaps[] = $next - $day;
        }
        $sameTime = count(array_unique(array_map(fn (Candidate $c) => $c->studentTime, $pick))) === 1;

        return [
            min(array_map(fn (Candidate $c) => $c->coverage, $pick)),
            $sameTime ? 1 : 0,
            count($pick) === 1 || min($gaps) >= 2 ? 1 : 0,
            array_sum(array_map(fn (Candidate $c) => $c->coverage, $pick)),
            -array_sum(array_map(fn (Candidate $c) => self::roundness($c->studentTime), $pick)),
        ];
    }

    /** 0 on the hour, 1 on the half hour, 2 otherwise. */
    private static function roundness(string $time): int
    {
        return match (substr($time, 3, 2)) {
            '00' => 0,
            '30' => 1,
            default => 2,
        };
    }

    /** @return iterable<list<int>> */
    private static function combinations(array $items, int $k, int $offset = 0, array $chosen = []): iterable
    {
        if (count($chosen) === $k) {
            yield $chosen;

            return;
        }
        for ($i = $offset; $i < count($items); $i++) {
            yield from self::combinations($items, $k, $i + 1, [...$chosen, $items[$i]]);
        }
    }

    /**
     * @param  list<list<Candidate>>  $lists
     * @return iterable<list<Candidate>>
     */
    private static function product(array $lists, array $prefix = []): iterable
    {
        if ($lists === []) {
            yield $prefix;

            return;
        }
        $first = array_shift($lists);
        foreach ($first as $item) {
            yield from self::product($lists, [...$prefix, $item]);
        }
    }
}
