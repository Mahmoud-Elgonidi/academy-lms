<?php

namespace Modules\Scheduling\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lessons for the people in them: a student's (for their family) or a
 * teacher's (for their week), with when, what, who and where each one
 * stands. Joins catalog and users directly (reads may).
 */
final class LessonList
{
    /** @return list<object> rows with id, starts_at, ends_at, status, type, funding_status, enrollment_id, teacher, student, subject_ar, subject_en, original_teacher (a substitute has it), cancel_reason */
    public function forStudent(string $studentId, CarbonImmutable $from, CarbonImmutable $to, bool $latestFirst = false): array
    {
        return $this->rows(fn (Builder $q) => $q->where('st.user_id', $studentId), $from, $to, $latestFirst);
    }

    /** @return list<object> */
    public function forTeacher(string $teacherId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->rows(fn (Builder $q) => $q->where('s.teacher_id', $teacherId), $from, $to);
    }

    /** @return list<object> */
    private function rows(callable $who, CarbonImmutable $from, CarbonImmutable $to, bool $latestFirst = false): array
    {
        return DB::table('sessions as s')
            ->join('session_participants as st', fn ($j) => $j->on('st.academy_id', '=', 's.academy_id')->on('st.session_id', '=', 's.id')->where('st.role', '=', 'student'))
            ->join('users as su', fn ($j) => $j->on('su.academy_id', '=', 's.academy_id')->on('su.id', '=', 'st.user_id'))
            ->join('users as t', fn ($j) => $j->on('t.academy_id', '=', 's.academy_id')->on('t.id', '=', 's.teacher_id'))
            ->join('subjects as sub', fn ($j) => $j->on('sub.academy_id', '=', 's.academy_id')->on('sub.id', '=', 's.subject_id'))
            ->leftJoin('users as ot', fn ($j) => $j->on('ot.academy_id', '=', 's.academy_id')->on('ot.id', '=', 's.original_teacher_id'))
            ->tap($who)
            ->where('s.starts_at', '>=', $from)->where('s.starts_at', '<', $to)
            ->orderBy('s.starts_at', $latestFirst ? 'desc' : 'asc')
            ->get(['s.id', 's.starts_at', 's.ends_at', 's.status', 's.type', 's.funding_status', 's.enrollment_id', 't.name as teacher', 'su.name as student', 'sub.name_ar as subject_ar', 'sub.name_en as subject_en',
                'ot.name as original_teacher', DB::raw("s.decision->>'reason' as cancel_reason")])
            ->map(function (object $r) {
                $r->starts_at = CarbonImmutable::parse($r->starts_at)->utc();
                $r->ends_at = CarbonImmutable::parse($r->ends_at)->utc();

                return $r;
            })->all();
    }
}
