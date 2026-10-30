<?php

namespace Modules\Scheduling\Domain\Availability;

/**
 * A weekly time with one teacher: anchored on the teacher's clock (weekday and
 * time), shown on the student's clock, free in $coverage of the $weeks weeks.
 * Proposed on the teacher's 15-minute grid, or typed at any minute.
 */
final readonly class Candidate
{
    /**
     * @param  list<int>  $occurrences  UTC start of every week's session in the horizon
     * @param  list<string>  $conflictDates  teacher-local dates (Y-m-d) where it is not free
     */
    public function __construct(
        public int $teacherWeekday,
        public string $teacherTime,
        public int $studentWeekday,
        public string $studentTime,
        public int $coverage,
        public int $weeks,
        public array $occurrences,
        public array $conflictDates,
        public bool $typed = false,
    ) {}

    /** The same time, marked as typed by hand ("another time…") rather than proposed. */
    public function asTyped(): self
    {
        return new self($this->teacherWeekday, $this->teacherTime, $this->studentWeekday, $this->studentTime,
            $this->coverage, $this->weeks, $this->occurrences, $this->conflictDates, true);
    }

    /** The student-clock end of a lesson of this length ("18:25"; past midnight it wraps). */
    public function studentEnd(int $durationMin): string
    {
        [$h, $m] = array_map('intval', explode(':', $this->studentTime));
        $end = ($h * 60 + $m + $durationMin) % (24 * 60);

        return sprintf('%02d:%02d', intdiv($end, 60), $end % 60);
    }

    public function key(): string
    {
        return $this->teacherWeekday.'|'.$this->teacherTime;
    }

    public function full(): bool
    {
        return $this->coverage === $this->weeks;
    }
}
