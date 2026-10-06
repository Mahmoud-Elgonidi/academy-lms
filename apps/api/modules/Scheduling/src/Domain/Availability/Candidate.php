<?php

namespace Modules\Scheduling\Domain\Availability;

/**
 * A weekly time with one teacher: anchored on the teacher's clock (weekday and
 * time), shown on the student's clock, free in $coverage of the $weeks weeks.
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
    ) {}

    public function key(): string
    {
        return $this->teacherWeekday.'|'.$this->teacherTime;
    }

    public function full(): bool
    {
        return $this->coverage === $this->weeks;
    }
}
