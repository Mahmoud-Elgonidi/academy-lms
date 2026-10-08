<?php

namespace Modules\Classroom\Queries;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Plans\UsagePeriod;

/**
 * The minutes of lessons held on our own video (a LiveKit room that opened)
 * in a period, by each lesson's length: what the plan's video minutes count.
 * Lessons on an outside link (Zoom, Meet) cost the platform nothing and are
 * not counted.
 */
final class VideoMinutes
{
    public function in(UsagePeriod $period): int
    {
        return (int) round((float) DB::table('sessions as s')
            ->join('rooms as r', fn ($j) => $j->on('r.academy_id', '=', 's.academy_id')->on('r.session_id', '=', 's.id'))
            ->where('r.provider', 'livekit')
            ->whereNotNull('r.started_at')
            ->where('s.status', '<>', 'cancelled')
            ->where('s.starts_at', '>=', $period->from)
            ->where('s.starts_at', '<', $period->to)
            ->sum(DB::raw('extract(epoch from s.ends_at - s.starts_at) / 60')));
    }
}
