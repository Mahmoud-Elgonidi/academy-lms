<?php

namespace Modules\Classroom\Recordings;

use Modules\Classroom\Contracts\RecordingHolds;

/**
 * Every reason another module has to keep a lesson's recordings (an open
 * complaint, a safeguarding concern), each tagged RecordingHolds::SOURCES by
 * the module that knows it. With none, nothing holds a recording.
 */
final class AllRecordingHolds implements RecordingHolds
{
    public function heldSessions(array $sessionIds): array
    {
        $held = [];
        if ($sessionIds === []) {
            return $held;
        }
        foreach (app()->tagged(RecordingHolds::SOURCES) as $source) {
            /** @var RecordingHolds $source */
            $held += $source->heldSessions($sessionIds);
        }

        return $held;
    }
}
