<?php

namespace Modules\Learning\Queries;

use Illuminate\Support\Facades\DB;

/** What Learning needs to know about a lesson, read from Scheduling's tables (reads may join across modules). */
final class SessionFacts
{
    /** @return object{id: string, teacher_id: string, enrollment_id: ?string, starts_at: string, ends_at: string, type: string, student_id: ?string}|null */
    public function find(string $sessionId): ?object
    {
        return DB::table('sessions as s')
            ->leftJoin('session_participants as p', fn ($j) => $j->on('p.academy_id', '=', 's.academy_id')->on('p.session_id', '=', 's.id')->where('p.role', 'student'))
            ->where('s.id', $sessionId)
            ->first(['s.id', 's.teacher_id', 's.enrollment_id', 's.starts_at', 's.ends_at', 's.type', 'p.user_id as student_id']);
    }
}
