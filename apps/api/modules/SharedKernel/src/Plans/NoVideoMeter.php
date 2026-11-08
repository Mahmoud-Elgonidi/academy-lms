<?php

namespace Modules\SharedKernel\Plans;

/** Nothing counted, nothing held: until Platform binds the academies' meters. */
final class NoVideoMeter implements VideoMeter
{
    public function ownVideoAllowed(): bool
    {
        return true;
    }

    public function roomClosed(RoomMinutes $room): int
    {
        return 0;
    }
}
