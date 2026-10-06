<?php

namespace Modules\Scheduling\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Minutes the teacher and the student were both in the classroom inside the
 * lesson's time: the teacher's stretches ∩ the student's ∩ the lesson, as
 * one tstzmultirange expression (docs/architecture/03, section 6). A stretch
 * still open counts until $until.
 */
final class TaughtMinutes
{
    public function hasSegments(string $sessionId): bool
    {
        return DB::table('attendance_segments')->where('session_id', $sessionId)->exists();
    }

    public function of(string $sessionId, CarbonImmutable $startsAt, CarbonImmutable $endsAt, CarbonImmutable $until): int
    {
        $stretches = fn (string $role) => "(select range_agg(tstzrange(joined_at, coalesce(left_at, :until_{$role}), '[)')) from attendance_segments where session_id = :session_{$role} and role = '{$role}')";

        $seconds = DB::selectOne(
            "select coalesce(sum(extract(epoch from upper(r) - lower(r))), 0) as seconds
               from unnest({$stretches('teacher')} * {$stretches('student')} * tstzmultirange(tstzrange(:starts, :ends, '[)'))) as r",
            [
                'until_teacher' => $until, 'session_teacher' => $sessionId,
                'until_student' => $until, 'session_student' => $sessionId,
                'starts' => $startsAt, 'ends' => $endsAt,
            ],
        )->seconds;

        return intdiv((int) round((float) $seconds), 60);
    }
}
