<?php

namespace Modules\Billing\Support;

use Carbon\CarbonImmutable;
use Modules\SharedKernel\Time\LocalTimeResolver;

/**
 * The days a revenue screen looks at, on the academy's clock: a payment on
 * the evening of 31 October in Cairo is October's, whatever UTC says. Days
 * are held as dates (midnight UTC, like payroll months) and turned into
 * instants only at the edges. A period never reaches past today, and is
 * compared with the one before it: this month so far with the same days of
 * last month, this year so far (or the last twelve months) with the same
 * days a year before, anything else with as many days just before.
 */
final readonly class RevenuePeriod
{
    public const PRESETS = ['this_month', 'last_month', 'last_90', 'this_year', 'last_12', 'custom'];

    /** Two years at most, so a custom range stays a screen and not a report. */
    public const MAX_DAYS = 731;

    private function __construct(
        public string $preset,
        public CarbonImmutable $first,
        public CarbonImmutable $last,
        public string $timezone,
    ) {}

    /** A preset, or a custom range of 'Y-m-d' days (put in order, kept to today and two years); anything else is this month. */
    public static function make(?string $preset, ?string $from, ?string $to, string $timezone, CarbonImmutable $now): self
    {
        $today = CarbonImmutable::parse($now->setTimezone($timezone)->toDateString(), 'UTC');
        $preset = in_array($preset, self::PRESETS, true) ? $preset : 'this_month';

        [$first, $last] = match ($preset) {
            'last_month' => [$today->startOfMonth()->subMonthNoOverflow(), $today->startOfMonth()->subDay()],
            'last_90' => [$today->subDays(89), $today],
            'this_year' => [$today->startOfYear(), $today],
            'last_12' => [$today->startOfMonth()->subMonthsNoOverflow(11), $today],
            'custom' => self::range($from, $to, $today),
            default => [$today->startOfMonth(), $today],
        };
        if ($first === null) {
            return self::make('this_month', null, null, $timezone, $now);
        }

        return new self($preset, $first, $last, $timezone);
    }

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable} */
    private static function range(?string $from, ?string $to, CarbonImmutable $today): array
    {
        $day = fn (?string $d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4))
            ? CarbonImmutable::parse($d, 'UTC') : null;
        $first = $day($from);
        $last = $day($to);
        if ($first === null || $last === null) {
            return [null, null];
        }
        if ($first->greaterThan($last)) {
            [$first, $last] = [$last, $first];
        }
        $last = $last->min($today);
        $first = $first->min($last)->max($last->subDays(self::MAX_DAYS - 1));

        return [$first, $last];
    }

    public function days(): int
    {
        return (int) $this->first->diffInDays($this->last) + 1;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} the first instant and the instant after the last day, in UTC */
    public function bounds(): array
    {
        return [$this->instant($this->first), $this->instant($this->last->addDay())];
    }

    /** The period it is compared with. */
    public function previous(): self
    {
        $span = $this->days() - 1;
        [$first, $last] = match ($this->preset) {
            'this_month' => [$this->first->subMonthNoOverflow(), $this->first->subMonthNoOverflow()->addDays($span)->min($this->first->subDay())],
            'last_month' => [$this->first->subMonthNoOverflow(), $this->first->subDay()],
            'this_year', 'last_12' => [$this->first->subYear(), $this->last->subYearNoOverflow()],
            default => [$this->first->subDays($span + 1), $this->first->subDay()],
        };

        return new self('custom', $first, $last, $this->timezone);
    }

    /** How the chart groups the days: one bar a day up to two months, a week (from Saturday) up to six, else a month. */
    public function step(): string
    {
        return match (true) {
            $this->days() <= 62 => 'day',
            $this->days() <= 186 => 'week',
            default => 'month',
        };
    }

    /**
     * The chart's bars in order, each its first and last day (inside the
     * period) and its instants.
     *
     * @return list<array{first: CarbonImmutable, last: CarbonImmutable, from: CarbonImmutable, to: CarbonImmutable}>
     */
    public function buckets(): array
    {
        $step = $this->step();
        $buckets = [];
        $day = $this->first;
        while ($day->lessThanOrEqualTo($this->last)) {
            $end = match ($step) {
                'day' => $day,
                'week' => $day->addDays((5 - $day->dayOfWeek + 7) % 7),      // up to Friday: weeks run Saturday to Friday
                default => $day->endOfMonth()->startOfDay(),
            };
            $end = $end->min($this->last);
            $buckets[] = ['first' => $day, 'last' => $end, 'from' => $this->instant($day), 'to' => $this->instant($end->addDay())];
            $day = $end->addDay();
        }

        return $buckets;
    }

    /** @return array<string, int> each day of the period ('Y-m-d') => the index of its bar */
    public function dayIndex(): array
    {
        $index = [];
        foreach ($this->buckets() as $i => $bucket) {
            for ($day = $bucket['first']; $day->lessThanOrEqualTo($bucket['last']); $day = $day->addDay()) {
                $index[$day->toDateString()] = $i;
            }
        }

        return $index;
    }

    /** The start of a day on the academy's clock, in UTC (where clocks jump at midnight, the first minute that exists). */
    private function instant(CarbonImmutable $day): CarbonImmutable
    {
        return (new LocalTimeResolver)->resolve($day->toDateString(), '00:00', $this->timezone)->utc;
    }
}
