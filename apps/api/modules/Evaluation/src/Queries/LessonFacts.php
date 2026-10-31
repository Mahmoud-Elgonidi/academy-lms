<?php

namespace Modules\Evaluation\Queries;

use Illuminate\Support\Facades\DB;

/**
 * What a rating needs to know about a lesson, read from Scheduling's tables
 * (reads may join across modules). A group lesson has a student per seat:
 * find() names the one asked for (else the first), and its status is what
 * that student's own seat came to (held for them only when they attended).
 */
final class LessonFacts
{
    /** @return object{id: string, academy_id: string, status: string, type: string, starts_at: string, ends_at: string, teacher_id: string, teacher: string, student_id: ?string, student: ?string, subject_ar: ?string, subject_en: ?string, group_id: ?string}|null */
    public function find(string $sessionId, ?string $studentId = null): ?object
    {
        return DB::table('sessions as s')
            ->join('users as t', fn ($j) => $j->on('t.academy_id', '=', 's.academy_id')->on('t.id', '=', 's.teacher_id'))
            ->leftJoinLateral(DB::table('session_participants as p')
                ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'p.academy_id')->on('u.id', '=', 'p.user_id'))
                ->whereColumn('p.academy_id', 's.academy_id')->whereColumn('p.session_id', 's.id')->where('p.role', 'student')
                ->when($studentId, fn ($q) => $q->where('p.user_id', $studentId))
                ->orderBy('p.user_id')->limit(1)->select(['p.user_id', 'p.outcome', 'u.name']), 'st')
            ->leftJoin('subjects as sub', fn ($j) => $j->on('sub.academy_id', '=', 's.academy_id')->on('sub.id', '=', 's.subject_id'))
            ->where('s.id', $sessionId)
            ->first(['s.id', 's.academy_id', 's.type', 's.starts_at', 's.ends_at', 's.teacher_id', 't.name as teacher', 's.group_id',
                DB::raw("case when s.group_id is not null and s.status = 'completed' and st.outcome is distinct from 'attended' then 'student_absent' else s.status end as status"),
                'st.user_id as student_id', 'st.name as student', 'sub.name_ar as subject_ar', 'sub.name_en as subject_en']);
    }

    /** @return list<string> the students of the lesson (each seat of a group lesson; those who attended, with $attended) */
    public function students(string $sessionId, bool $attended = false): array
    {
        return DB::table('session_participants as p')
            ->join('sessions as s', fn ($j) => $j->on('s.academy_id', '=', 'p.academy_id')->on('s.id', '=', 'p.session_id'))
            ->where('p.session_id', $sessionId)->where('p.role', 'student')
            ->when($attended, fn ($q) => $q->where(fn ($w) => $w->whereNull('s.group_id')->orWhere('p.outcome', 'attended')))
            ->orderBy('p.user_id')->pluck('p.user_id')->all();
    }
}
