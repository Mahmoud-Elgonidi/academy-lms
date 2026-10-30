<?php

namespace Modules\Scheduling\Domain\Availability;

/**
 * One teacher's free time over the matching horizon, and how busy their
 * shifts are. The parts behind it (the shifts before leave and holidays,
 * the leave, the holidays, lessons, others' holds, days at the daily cap)
 * are kept when known, so a time that does not fit can say why.
 */
final readonly class TeacherAvailability
{
    public IntervalSet $planned;

    public IntervalSet $off;

    public IntervalSet $closed;

    public IntervalSet $booked;

    public IntervalSet $held;

    public IntervalSet $capped;

    public function __construct(
        public string $teacherId,
        public string $timezone,
        public IntervalSet $shifts,
        public IntervalSet $free,
        ?IntervalSet $planned = null,
        ?IntervalSet $off = null,
        ?IntervalSet $closed = null,
        ?IntervalSet $booked = null,
        ?IntervalSet $held = null,
        ?IntervalSet $capped = null,
    ) {
        $this->planned = $planned ?? $shifts;
        $this->off = $off ?? new IntervalSet;
        $this->closed = $closed ?? new IntervalSet;
        $this->booked = $booked ?? new IntervalSet;
        $this->held = $held ?? new IntervalSet;
        $this->capped = $capped ?? new IntervalSet;
    }

    /** Share of shift time already booked (0–1). */
    public function utilisation(): float
    {
        $shift = $this->shifts->totalSeconds();

        return $shift === 0 ? 1.0 : round(1 - $this->free->totalSeconds() / $shift, 3);
    }

    /**
     * Why [start, end) is not free (ExactCheck's reasons): the academy is
     * closed, the teacher is on leave, it is outside their shifts, they
     * teach then, someone else holds it, or their day is full.
     */
    public function whyBusy(int $start, int $end): string
    {
        return match (true) {
            $this->closed->overlaps($start, $end) => ExactCheck::HOLIDAY,
            $this->off->overlaps($start, $end) => ExactCheck::TIME_OFF,
            ! $this->planned->contains($start, $end) => ExactCheck::SHIFTS,
            $this->held->overlaps($start, $end) && ! $this->booked->overlaps($start, $end) => ExactCheck::HELD,
            $this->capped->overlaps($start, $end) && ! $this->booked->overlaps($start, $end) => ExactCheck::CAP,
            // Taken by a lesson (or by what is not known here).
            default => ExactCheck::BUSY,
        };
    }
}
