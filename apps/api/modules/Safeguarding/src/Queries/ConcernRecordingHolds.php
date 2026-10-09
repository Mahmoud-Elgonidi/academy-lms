<?php

namespace Modules\Safeguarding\Queries;

use Modules\Classroom\Contracts\RecordingHolds;
use Modules\Safeguarding\Enums\ConcernStatus;
use Modules\Safeguarding\Models\Concern;

/** A lesson named in a concern that is not closed keeps its recordings (the screen says only "held for a review"). */
final class ConcernRecordingHolds implements RecordingHolds
{
    public function heldSessions(array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        return Concern::query()->whereIn('session_id', $sessionIds)->where('status', '<>', ConcernStatus::Closed->value)
            ->pluck('session_id')->unique()->mapWithKeys(fn (string $id) => [$id => __('safeguarding::concerns.recording_hold')])->all();
    }
}
