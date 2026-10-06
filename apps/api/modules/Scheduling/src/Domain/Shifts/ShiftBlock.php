<?php

namespace Modules\Scheduling\Domain\Shifts;

use InvalidArgumentException;

/** One working block in a weekly schedule: an ISO weekday and a wall-clock span in the teacher's timezone. */
final readonly class ShiftBlock
{
    public const DAY_MIN = 1440;

    public function __construct(
        public int $weekday,     // 1 Monday … 7 Sunday
        public int $startMin,    // minutes after midnight
        public int $durationMin,
    ) {
        if ($weekday < 1 || $weekday > 7) {
            throw new InvalidArgumentException("Weekday {$weekday} is not 1–7.");
        }
        if ($startMin < 0 || $startMin >= self::DAY_MIN || $startMin % 5 !== 0) {
            throw new InvalidArgumentException('A block starts on a 5-minute mark within the day.');
        }
        if ($durationMin <= 0 || $durationMin % 5 !== 0 || $startMin + $durationMin > self::DAY_MIN) {
            throw new InvalidArgumentException('A block lasts a positive multiple of 5 minutes and ends by midnight.');
        }
    }

    /** @param  array{weekday: int|string, start: string, duration_min?: int|string, end?: string}  $data */
    public static function fromArray(array $data): self
    {
        $start = self::minutes($data['start']);
        $duration = isset($data['end'])
            ? ($data['end'] === '24:00' || $data['end'] === '00:00' ? self::DAY_MIN : self::minutes($data['end'])) - $start
            : (int) $data['duration_min'];

        return new self((int) $data['weekday'], $start, $duration);
    }

    public function endMin(): int
    {
        return $this->startMin + $this->durationMin;
    }

    public function start(): string
    {
        return self::clock($this->startMin);
    }

    public function end(): string
    {
        return self::clock($this->endMin());
    }

    public function overlaps(self $other): bool
    {
        return $this->weekday === $other->weekday && $this->startMin < $other->endMin() && $other->startMin < $this->endMin();
    }

    /** Does [startMin, startMin + duration) on this weekday fit inside the block? */
    public function contains(int $weekday, int $startMin, int $durationMin): bool
    {
        return $weekday === $this->weekday && $startMin >= $this->startMin && $startMin + $durationMin <= $this->endMin();
    }

    /** @return array{weekday: int, start: string, end: string, duration_min: int} */
    public function toArray(): array
    {
        return ['weekday' => $this->weekday, 'start' => $this->start(), 'end' => $this->end(), 'duration_min' => $this->durationMin];
    }

    public static function minutes(string $clock): int
    {
        if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(:\d\d)?$/', $clock, $m)) {
            throw new InvalidArgumentException("Bad time [{$clock}].");
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }

    public static function clock(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
