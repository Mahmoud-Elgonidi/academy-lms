<?php

namespace Modules\SharedKernel\Plans;

/**
 * The academy's video minutes, for the classroom (docs/architecture/02,
 * section 11): a room on our own video that closed is counted once into the
 * month (again after it opened anew, only what it added), and under the hard
 * limit, with nothing left, a new lesson opens on the teacher's outside link
 * instead. Platform binds it; until then nothing is counted or held.
 */
interface VideoMeter
{
    /** Whether a lesson that has not started may open on our own video now (no: the hard limit at zero). */
    public function ownVideoAllowed(): bool;

    /** @return int the minutes this room counts for in all, now */
    public function roomClosed(RoomMinutes $room): int;
}
