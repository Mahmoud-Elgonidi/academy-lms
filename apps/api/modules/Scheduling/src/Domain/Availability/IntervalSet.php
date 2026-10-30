<?php

namespace Modules\Scheduling\Domain\Availability;

/**
 * Sorted, non-overlapping [start, end) intervals in Unix seconds (UTC).
 * Free time is shifts minus bookings minus holds; matching asks whether a
 * whole session fits inside.
 */
final readonly class IntervalSet
{
    /** @var list<array{0: int, 1: int}> */
    public array $intervals;

    /** @param  list<array{0: int, 1: int}>  $intervals */
    public function __construct(array $intervals = [])
    {
        $intervals = array_values(array_filter($intervals, fn (array $i) => $i[1] > $i[0]));
        usort($intervals, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $merged = [];
        foreach ($intervals as [$start, $end]) {
            $last = count($merged) - 1;
            if ($last >= 0 && $start <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $end);
            } else {
                $merged[] = [$start, $end];
            }
        }
        $this->intervals = $merged;
    }

    public function minus(self $other): self
    {
        $result = [];
        $j = 0;
        $cut = $other->intervals;

        foreach ($this->intervals as [$start, $end]) {
            $cursor = $start;
            while ($j < count($cut) && $cut[$j][1] <= $cursor) {
                $j++;
            }
            $k = $j;
            while ($k < count($cut) && $cut[$k][0] < $end) {
                if ($cut[$k][0] > $cursor) {
                    $result[] = [$cursor, $cut[$k][0]];
                }
                $cursor = max($cursor, $cut[$k][1]);
                $k++;
            }
            if ($cursor < $end) {
                $result[] = [$cursor, $end];
            }
        }

        return new self($result);
    }

    public function contains(int $start, int $end): bool
    {
        foreach ($this->intervals as [$from, $to]) {
            if ($from <= $start && $end <= $to) {
                return true;
            }
            if ($from > $start) {
                return false;
            }
        }

        return false;
    }

    /** Does any interval share time with [start, end)? */
    public function overlaps(int $start, int $end): bool
    {
        foreach ($this->intervals as [$from, $to]) {
            if ($from < $end && $start < $to) {
                return true;
            }
            if ($from >= $end) {
                return false;
            }
        }

        return false;
    }

    public function totalSeconds(int $from = PHP_INT_MIN, int $to = PHP_INT_MAX): int
    {
        $total = 0;
        foreach ($this->intervals as [$start, $end]) {
            $total += max(0, min($end, $to) - max($start, $from));
        }

        return $total;
    }

    public function overlapSeconds(self $other): int
    {
        return $this->totalSeconds() - $this->minus($other)->totalSeconds();
    }
}
