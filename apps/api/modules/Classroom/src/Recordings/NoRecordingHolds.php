<?php

namespace Modules\Classroom\Recordings;

use Modules\Classroom\Contracts\RecordingHolds;

/** Without Support: nothing outside the classroom holds a recording. */
final class NoRecordingHolds implements RecordingHolds
{
    public function heldSessions(array $sessionIds): array
    {
        return [];
    }
}
