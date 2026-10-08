<?php

namespace Modules\Support\Queries;

use Illuminate\Support\Facades\DB;
use Modules\Classroom\Contracts\RecordingHolds;

/**
 * A lesson's recordings stay while a complaint about it is open: waiting for
 * the academy's decision, or not closed yet (the classroom asks before it
 * removes one whose days are up).
 */
final class ComplaintRecordingHolds implements RecordingHolds
{
    public function heldSessions(array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        return DB::table('tickets')->where('kind', 'complaint')->whereIn('session_id', $sessionIds)
            ->where(fn ($q) => $q->where('complaint_status', 'pending')->orWhere('status', '<>', 'closed'))
            ->orderBy('number')->get(['session_id', 'number'])
            ->mapWithKeys(fn ($t) => [$t->session_id => __('support::tickets.recording_hold', ['number' => $t->number])])->all();
    }
}
