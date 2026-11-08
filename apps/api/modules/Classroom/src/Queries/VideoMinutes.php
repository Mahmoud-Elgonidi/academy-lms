<?php

namespace Modules\Classroom\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Plans\RoomMinutes;

/**
 * The minutes of lessons on our own video still under way: each open LiveKit
 * room's time so far, rounded up, never more than its lesson's length and the
 * margin. The academy's month shows them while they run; when the room
 * closes they go into the usage ledger at its actual open time (MeterRoom).
 * Lessons on an outside link (Zoom, Meet) count nothing.
 */
final class VideoMinutes
{
    public function running(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        return (int) DB::table('rooms as r')
            ->join('sessions as s', fn ($j) => $j->on('s.academy_id', '=', 'r.academy_id')->on('s.id', '=', 'r.session_id'))
            ->where('r.provider', 'livekit')->whereNotNull('r.started_at')->whereNull('r.finished_at')->whereNull('r.metered_at')
            ->where('r.started_at', '<', $now)
            ->sum(DB::raw('least(ceil(extract(epoch from ('.$this->quote($now).'::timestamptz - r.started_at)) / 60), round(extract(epoch from s.ends_at - s.starts_at) / 60) + '.RoomMinutes::MARGIN_MINUTES.')'));
    }

    private function quote(CarbonImmutable $at): string
    {
        return DB::getPdo()->quote($at->utc()->toIso8601String());
    }
}
