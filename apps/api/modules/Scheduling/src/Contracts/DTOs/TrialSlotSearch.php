<?php

namespace Modules\Scheduling\Contracts\DTOs;

use Carbon\CarbonImmutable;

/**
 * What a family looks for in a free trial lesson they book themselves: the
 * subject, the learner's age (a child is taught only by teachers cleared for
 * minors), a teacher's gender if they asked for one, on their own clock,
 * from a day for a number of days; for a second trial, never the first
 * one's teacher.
 */
final readonly class TrialSlotSearch
{
    public const GENDERS = ['female', 'male'];

    public function __construct(
        public string $subjectId,
        public string $timezone,                 // IANA: the family's clock (the grid of starts and the days)
        public ?string $ageGroup = null,         // People's AgeGroup: teachers who teach that age (or any)
        public bool $forMinor = false,           // under 18: only teachers People clears for minors
        public ?string $teacherGender = null,    // female | male: only such teachers; null: anyone
        public ?CarbonImmutable $from = null,    // the first day (on their clock); null: today
        public int $days = 14,
        public int $durationMin = 30,
        public ?string $teacherId = null,        // only this teacher (moving a trial booked with them)
        public int $noticeHours = 3,             // no start sooner than this many hours from now (the academy's trial_notice_hours)
        public ?string $excludeTeacherId = null, // never this teacher (a second trial: "not the right teacher?")
    ) {}

    /** The same search over one day of the family's clock: the day this start is on. */
    public function onDayOf(CarbonImmutable $startsAt): self
    {
        return new self($this->subjectId, $this->timezone, $this->ageGroup, $this->forMinor, $this->teacherGender,
            $startsAt->setTimezone($this->timezone)->startOfDay(), 1, $this->durationMin, $this->teacherId, $this->noticeHours, $this->excludeTeacherId);
    }
}
