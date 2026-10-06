<?php

namespace Modules\Scheduling\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\TeacherTimeOff;

/**
 * The lessons a teacher's time off touches, and where each stands: still
 * theirs (it needs cover), given to a substitute, or cancelled with a makeup.
 */
final class TimeOffCover
{
    /** @return list<Session> with teacher_name, original_name and student_name */
    public function lessons(TeacherTimeOff $timeOff): array
    {
        return Session::query()
            ->join('users as t', fn ($j) => $j->on('t.academy_id', '=', 'sessions.academy_id')->on('t.id', '=', 'sessions.teacher_id'))
            ->leftJoin('session_participants as p', fn ($j) => $j->on('p.academy_id', '=', 'sessions.academy_id')->on('p.session_id', '=', 'sessions.id')->where('p.role', '=', 'student'))
            ->leftJoin('users as st', fn ($j) => $j->on('st.academy_id', '=', 'p.academy_id')->on('st.id', '=', 'p.user_id'))
            ->join('subjects as sub', fn ($j) => $j->on('sub.academy_id', '=', 'sessions.academy_id')->on('sub.id', '=', 'sessions.subject_id'))
            ->where(fn ($q) => $q->where('sessions.teacher_id', $timeOff->teacher_id)->orWhere('sessions.original_teacher_id', $timeOff->teacher_id))
            ->where('sessions.starts_at', '<', $timeOff->ends_at)->where('sessions.ends_at', '>', $timeOff->starts_at)
            ->orderBy('sessions.starts_at')
            ->get(['sessions.*', 't.name as teacher_name', 'st.name as student_name', 'sub.name_ar as subject_ar', 'sub.name_en as subject_en'])
            ->all();
    }

    /** Lessons still with a teacher who has approved time off then, from now on. */
    public static function uncovered(?CarbonImmutable $before = null): int
    {
        return (int) self::uncoveredQuery($before)->count();
    }

    /** @return Builder sessions (s) joined to the leave (o) */
    public static function uncoveredQuery(?CarbonImmutable $before = null, ?CarbonImmutable $now = null)
    {
        return DB::table('sessions as s')
            ->join('teacher_time_off as o', fn ($j) => $j->on('o.academy_id', '=', 's.academy_id')->on('o.teacher_id', '=', 's.teacher_id')
                ->where('o.status', '=', 'approved')->whereColumn('o.starts_at', '<', 's.ends_at')->whereColumn('o.ends_at', '>', 's.starts_at'))
            ->where('s.status', 'scheduled')
            ->where('s.starts_at', '>', ($now ?? CarbonImmutable::now())->utc())
            ->when($before, fn ($q) => $q->where('s.starts_at', '<=', $before->utc()));
    }
}
