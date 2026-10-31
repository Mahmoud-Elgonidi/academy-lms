<?php

namespace Modules\Learning\Queries;

use Illuminate\Support\Facades\DB;

/** What Learning needs to know about a lesson, read from Scheduling's tables (reads may join across modules). */
final class SessionFacts
{
    /** @return object{id: string, teacher_id: string, enrollment_id: ?string, group_id: ?string, starts_at: string, ends_at: string, type: string, student_id: ?string}|null */
    public function find(string $sessionId): ?object
    {
        return DB::table('sessions as s')
            ->leftJoinLateral(DB::table('session_participants as p')
                ->whereColumn('p.academy_id', 's.academy_id')->whereColumn('p.session_id', 's.id')->where('p.role', 'student')
                ->orderBy('p.user_id')->limit(1)->select(['p.user_id', 'p.enrollment_id']), 'p')
            ->where('s.id', $sessionId)
            ->first(['s.id', 's.teacher_id', DB::raw('coalesce(s.enrollment_id, p.enrollment_id) as enrollment_id'), 's.group_id', 's.starts_at', 's.ends_at', 's.type', 'p.user_id as student_id']);
    }

    /**
     * A group lesson's seats (docs/architecture/02, "الحصص الجماعية"): each student, their own course and outcome.
     *
     * @return list<object{student_id: string, enrollment_id: ?string, outcome: ?string}>
     */
    public function seats(string $sessionId): array
    {
        return DB::table('session_participants')->where('session_id', $sessionId)->where('role', 'student')->whereNotNull('enrollment_id')
            ->orderBy('user_id')->get(['user_id as student_id', 'enrollment_id', 'outcome'])->all();
    }
}
