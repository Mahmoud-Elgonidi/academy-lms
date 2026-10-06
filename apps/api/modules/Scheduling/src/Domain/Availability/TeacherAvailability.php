<?php

namespace Modules\Scheduling\Domain\Availability;

/** One teacher's free time over the matching horizon, and how busy their shifts are. */
final readonly class TeacherAvailability
{
    public function __construct(
        public string $teacherId,
        public string $timezone,
        public IntervalSet $shifts,
        public IntervalSet $free,
    ) {}

    /** Share of shift time already booked (0–1). */
    public function utilisation(): float
    {
        $shift = $this->shifts->totalSeconds();

        return $shift === 0 ? 1.0 : round(1 - $this->free->totalSeconds() / $shift, 3);
    }
}
