<?php

namespace Modules\Scheduling\Privacy;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * A person's lessons (as a student: when, what, with whom, how long they
 * were in; as a teacher: when and what, without the students' names), the
 * groups they learned in (subject, teacher, from and to, never classmates),
 * the disputes they raised and a teacher's time off. Lessons still to come hold
 * an erasure back. Erasing blanks their words in a dispute and in time-off
 * notes and drops held times; the lessons stay, for credit, pay and the
 * academy's numbers.
 */
final class LessonsData implements PersonalDataSource
{
    public function key(): string
    {
        return 'lessons';
    }

    public function export(string $userId): PersonalDataPart
    {
        $subject = DB::raw('coalesce(sub.name_ar, sub.name_en) as subject');

        return new PersonalDataPart([
            'as_student' => DB::table('session_participants as p')
                ->join('sessions as s', fn ($j) => $j->on('s.academy_id', '=', 'p.academy_id')->on('s.id', '=', 'p.session_id'))
                ->join('users as t', fn ($j) => $j->on('t.academy_id', '=', 's.academy_id')->on('t.id', '=', 's.teacher_id'))
                ->leftJoin('subjects as sub', fn ($j) => $j->on('sub.academy_id', '=', 's.academy_id')->on('sub.id', '=', 's.subject_id'))
                ->where('p.user_id', $userId)->where('p.role', 'student')->orderBy('s.starts_at')
                ->get(['s.starts_at', 's.ends_at', 's.type', 's.status', $subject, 't.name as teacher', 'p.attended_min'])->map(fn (object $r) => (array) $r)->all(),
            'groups' => DB::table('group_members as m')
                ->join('lesson_groups as g', fn ($j) => $j->on('g.academy_id', '=', 'm.academy_id')->on('g.id', '=', 'm.group_id'))
                ->join('users as t', fn ($j) => $j->on('t.academy_id', '=', 'g.academy_id')->on('t.id', '=', 'g.teacher_id'))
                ->leftJoin('subjects as sub', fn ($j) => $j->on('sub.academy_id', '=', 'g.academy_id')->on('sub.id', '=', 'g.subject_id'))
                ->where('m.student_id', $userId)->orderBy('m.joined_on')
                ->get(['m.joined_on', 'm.left_on', $subject, 't.name as teacher'])->map(fn (object $r) => (array) $r)->all(),
            'as_teacher' => DB::table('sessions as s')
                ->leftJoin('subjects as sub', fn ($j) => $j->on('sub.academy_id', '=', 's.academy_id')->on('sub.id', '=', 's.subject_id'))
                ->where('s.teacher_id', $userId)->orderBy('s.starts_at')
                ->get(['s.starts_at', 's.ends_at', 's.type', 's.status', $subject])->map(fn (object $r) => (array) $r)->all(),
            'disputes' => DB::table('session_disputes as d')->join('sessions as s', fn ($j) => $j->on('s.academy_id', '=', 'd.academy_id')->on('s.id', '=', 'd.session_id'))
                ->where('d.raised_by', $userId)->orderBy('d.created_at')
                ->get(['s.starts_at as lesson_at', 'd.reason', 'd.note', 'd.status', 'd.outcome_before', 'd.outcome_after', 'd.decision_note as answer', 'd.created_at', 'd.decided_at'])
                ->map(fn (object $r) => (array) $r)->all(),
            'time_off' => DB::table('teacher_time_off')->where('teacher_id', $userId)->orderBy('starts_at')
                ->get(['starts_at', 'ends_at', 'all_day', 'reason', 'note', 'status', 'decision_note'])->map(fn (object $r) => (array) $r)->all(),
        ]);
    }

    public function blockers(string $userId): array
    {
        $ahead = DB::table('sessions as s')->whereIn('s.status', ['scheduled', 'in_progress'])->where('s.ends_at', '>', now())
            ->where(fn ($q) => $q->where('s.teacher_id', $userId)->orWhereExists(fn ($p) => $p->from('session_participants as p')
                ->whereColumn('p.academy_id', 's.academy_id')->whereColumn('p.session_id', 's.id')->where('p.user_id', $userId)))
            ->count();

        return $ahead === 0 ? [] : [trans_choice('scheduling::privacy.lessons_ahead', $ahead, ['count' => $ahead])];
    }

    public function erase(string $userId): array
    {
        return [
            'dispute_words' => DB::table('session_disputes')->where('raised_by', $userId)->where('note', '!=', '')->update(['note' => '', 'updated_at' => now()]),
            'time_off_notes' => DB::table('teacher_time_off')->where('teacher_id', $userId)->whereNotNull('note')->update(['note' => null, 'updated_at' => now()]),
            'held_times' => DB::table('booking_holds')->where(fn ($q) => $q->where('student_id', $userId)->orWhere('teacher_id', $userId))->delete(),
        ];
    }
}
