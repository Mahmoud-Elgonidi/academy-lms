<?php

namespace Modules\Scheduling\Domain\Shifts;

/**
 * A full weekly schedule in one timezone. Proposals always carry a whole
 * week, never a patch, so validation and impact look at the result.
 */
final readonly class WeeklyShifts
{
    /** @var list<ShiftBlock> sorted by weekday, then start */
    public array $blocks;

    /** @param  list<ShiftBlock>  $blocks */
    public function __construct(array $blocks, public string $timezone)
    {
        usort($blocks, fn (ShiftBlock $a, ShiftBlock $b) => [$a->weekday, $a->startMin] <=> [$b->weekday, $b->startMin]);
        $this->blocks = array_values($blocks);
    }

    /** @param  list<array<string, mixed>>  $rows */
    public static function fromArray(array $rows, string $timezone): self
    {
        return new self(array_map(fn (array $row) => ShiftBlock::fromArray($row), $rows), $timezone);
    }

    public static function empty(string $timezone): self
    {
        return new self([], $timezone);
    }

    public function weeklyMinutes(): int
    {
        return array_sum(array_map(fn (ShiftBlock $b) => $b->durationMin, $this->blocks));
    }

    /**
     * Problems that stop a submission, as translation keys with parameters.
     *
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    public function problems(int $minBlockMin): array
    {
        $problems = [];

        foreach ($this->blocks as $i => $block) {
            if ($block->durationMin < $minBlockMin) {
                $problems[] = ['block_too_short', ['day' => $block->weekday, 'start' => $block->start(), 'min' => $minBlockMin]];
            }
            foreach (array_slice($this->blocks, $i + 1) as $other) {
                if ($block->overlaps($other)) {
                    $problems[] = ['blocks_overlap', ['day' => $block->weekday, 'start' => $other->start()]];
                }
            }
        }

        return $problems;
    }

    /** Is a session of $durationMin starting at $startMin (wall clock) on $weekday inside one block? */
    public function covers(int $weekday, int $startMin, int $durationMin): bool
    {
        foreach ($this->blocks as $block) {
            if ($block->contains($weekday, $startMin, $durationMin)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<ShiftBlock> */
    public function on(int $weekday): array
    {
        return array_values(array_filter($this->blocks, fn (ShiftBlock $b) => $b->weekday === $weekday));
    }

    /**
     * Minute-by-minute comparison with another week, collapsed into spans:
     * what stays, what is added and what is removed, per weekday.
     *
     * @return array<int, list<array{kind: string, start: string, end: string, minutes: int}>>
     */
    public function compareTo(self $before): array
    {
        $result = [];

        for ($day = 1; $day <= 7; $day++) {
            $now = $this->minuteSet($day);
            $was = $before->minuteSet($day);
            $spans = [];
            $current = null;

            for ($m = 0; $m <= ShiftBlock::DAY_MIN; $m += 5) {
                $kind = $m === ShiftBlock::DAY_MIN ? null : match (true) {
                    isset($now[$m]) && isset($was[$m]) => 'same',
                    isset($now[$m]) => 'added',
                    isset($was[$m]) => 'removed',
                    default => null,
                };

                if ($current !== null && $current['kind'] !== $kind) {
                    $current['end'] = ShiftBlock::clock($m);
                    $current['minutes'] = $m - ShiftBlock::minutes($current['start']);
                    $spans[] = $current;
                    $current = null;
                }
                if ($kind !== null && $current === null) {
                    $current = ['kind' => $kind, 'start' => ShiftBlock::clock($m), 'end' => '', 'minutes' => 0];
                }
            }

            $result[$day] = $spans;
        }

        return $result;
    }

    /** Minutes this week has that $before did not (and the reverse). */
    public function addedAndRemovedMinutes(self $before): array
    {
        $added = $removed = 0;
        for ($day = 1; $day <= 7; $day++) {
            $now = $this->minuteSet($day);
            $was = $before->minuteSet($day);
            $added += 5 * count(array_diff_key($now, $was));
            $removed += 5 * count(array_diff_key($was, $now));
        }

        return [$added, $removed];
    }

    /** @return list<array{weekday: int, start: string, end: string, duration_min: int}> */
    public function toArray(): array
    {
        return array_map(fn (ShiftBlock $b) => $b->toArray(), $this->blocks);
    }

    /** @return array<int, true> keys are 5-minute marks covered on $day */
    private function minuteSet(int $day): array
    {
        $set = [];
        foreach ($this->on($day) as $block) {
            for ($m = $block->startMin; $m < $block->endMin(); $m += 5) {
                $set[$m] = true;
            }
        }

        return $set;
    }
}
