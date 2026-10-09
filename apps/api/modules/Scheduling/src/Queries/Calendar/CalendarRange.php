<?php

namespace Modules\Scheduling\Queries\Calendar;

use Carbon\CarbonImmutable;
use Modules\SharedKernel\Time\LocalTimeResolver;

/**
 * The days a calendar shows, on the viewer's clock: one day, a week from
 * Saturday to Friday (the academy's week, as the challenges count it), or a
 * month. Days are held as dates (midnight UTC) and turned into instants only
 * at the edges, so a lesson at 23:30 in Riyadh is that evening's, whatever
 * UTC says.
 */
final readonly class CalendarRange
{
    public const UNITS = ['day', 'week', 'month'];

    private function __construct(
        public string $unit,
        public CarbonImmutable $first,
        public CarbonImmutable $last,
        public string $timezone,
    ) {}

    /** The unit around a 'Y-m-d' day (today on the viewer's clock when missing or not a day). */
    public static function make(?string $unit, ?string $date, string $timezone, CarbonImmutable $now): self
    {
        $unit = in_array($unit, self::UNITS, true) ? $unit : 'week';
        $day = self::day($date) ?? CarbonImmutable::parse($now->setTimezone($timezone)->toDateString(), 'UTC');

        return match ($unit) {
            'day' => new self('day', $day, $day, $timezone),
            'month' => new self('month', $day->startOfMonth(), $day->endOfMonth()->startOfDay(), $timezone),
            default => new self('week', self::saturday($day), self::saturday($day)->addDays(6), $timezone),
        };
    }

    public static function day(?string $date): ?CarbonImmutable
    {
        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))
            ? CarbonImmutable::parse($date, 'UTC') : null;
    }

    /** The Saturday on or before the day. */
    public static function saturday(CarbonImmutable $day): CarbonImmutable
    {
        return $day->subDays(($day->dayOfWeek + 1) % 7);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} the first instant and the instant after the last day, in UTC */
    public function bounds(): array
    {
        return [$this->instant($this->first), $this->instant($this->last->addDay())];
    }

    /** Midnight of a day on the viewer's clock, in UTC (a midnight lost to a clock change is the first minute after it). */
    public function instant(CarbonImmutable $day): CarbonImmutable
    {
        return (new LocalTimeResolver)->resolve($day->toDateString(), '00:00', $this->timezone)->utc;
    }

    /** @return list<CarbonImmutable> every day shown, as dates */
    public function days(): array
    {
        $days = [];
        for ($d = $this->first; $d->lessThanOrEqualTo($this->last); $d = $d->addDay()) {
            $days[] = $d;
        }

        return $days;
    }

    /** The month's grid: whole weeks from the Saturday on or before the 1st. @return list<list<CarbonImmutable>> */
    public function weeks(): array
    {
        $weeks = [];
        for ($d = self::saturday($this->first); $d->lessThanOrEqualTo($this->last); $d = $d->addWeek()) {
            $weeks[] = array_map(fn (int $i) => $d->addDays($i), range(0, 6));
        }

        return $weeks;
    }

    /** The same unit just before (-1) or after (1). */
    public function step(int $by): string
    {
        $day = match ($this->unit) {
            'day' => $this->first->addDays($by),
            'month' => $this->first->addMonthsNoOverflow($by),
            default => $this->first->addWeeks($by),
        };

        return $day->toDateString();
    }

    public function contains(CarbonImmutable $day): bool
    {
        return $day->between($this->first, $this->last);
    }
}
