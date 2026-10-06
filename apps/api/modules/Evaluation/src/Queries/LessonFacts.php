<?php

namespace Modules\Evaluation\Queries;

use Illuminate\Support\Facades\DB;

/** What a rating needs to know about a lesson, read from Scheduling's tables (reads may join across modules). */
final class LessonFacts
{
    /** @return object{id: string, academy_id: string, status: string, type: string, starts_at: string, ends_at: string, teacher_id: string, teacher: string, student_id: ?string, student: ?string, subject_ar: ?string, subject_en: ?string}|null */
    public function find(string $sessionId): ?object
    {
        return DB::table('sessions as s')
            ->join('users as t', fn ($j) => $j->on('t.academy_id', '=', 's.academy_id')->on('t.id', '=', 's.teacher_id'))
            ->leftJoin('session_participants as p', fn ($j) => $j->on('p.academy_id', '=', 's.academy_id')->on('p.session_id', '=', 's.id')->where('p.role', 'student'))
            ->leftJoin('users as u', fn ($j) => $j->on('u.academy_id', '=', 'p.academy_id')->on('u.id', '=', 'p.user_id'))
            ->leftJoin('subjects as sub', fn ($j) => $j->on('sub.academy_id', '=', 's.academy_id')->on('sub.id', '=', 's.subject_id'))
            ->where('s.id', $sessionId)
            ->first(['s.id', 's.academy_id', 's.status', 's.type', 's.starts_at', 's.ends_at', 's.teacher_id', 't.name as teacher',
                'p.user_id as student_id', 'u.name as student', 'sub.name_ar as subject_ar', 'sub.name_en as subject_en']);
    }
}
