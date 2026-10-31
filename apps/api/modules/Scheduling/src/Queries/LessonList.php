<?php

namespace Modules\Scheduling\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\AttendanceService;
use Modules\Scheduling\Support\CourseDays;

/**
 * Lessons for the people in them: a student's (for their family) or a
 * teacher's (for their week), with when, what, who and where each one
 * stands. Joins catalog and users directly (reads may). One row a lesson: a
 * group lesson is the student's own seat in it (its outcome and dispute,
 * and the classmates' first names only), or for its teacher the group with
 * every student seated.
 */
final class LessonList
{
    /** @return list<object> rows with id, starts_at, ends_at, status, type, funding_status, enrollment_id, teacher, student, subject_ar, subject_en, original_teacher (a substitute has it), cancel_reason, dispute_status, dispute_side, dispute_reason, dispute_answer (its one dispute, if any), group_id, capacity, students (seated), seat_outcome, seat_active, classmates (first names, a group lesson's others) */
    public function forStudent(string $studentId, CarbonImmutable $from, CarbonImmutable $to, bool $latestFirst = false): array
    {
        $rows = $this->rows(fn (Builder $q) => $q
            ->join('session_participants as st', fn ($j) => $j->on('st.academy_id', '=', 's.academy_id')->on('st.session_id', '=', 's.id')
                ->where('st.role', '=', 'student')->where('st.user_id', '=', $studentId))
            ->join('users as su', fn ($j) => $j->on('su.academy_id', '=', 's.academy_id')->on('su.id', '=', 'st.user_id'))
            // The student's own dispute (a group lesson has one per seat).
            ->leftJoin('session_disputes as d', fn ($j) => $j->on('d.academy_id', '=', 's.academy_id')->on('d.session_id', '=', 's.id')->on('d.student_id', '=', 'st.user_id'))
            ->addSelect(['su.name as student', 'st.outcome as seat_outcome', 'st.is_active as seat_active', DB::raw('coalesce(s.enrollment_id, st.enrollment_id) as enrollment_id')]),
            $from, $to, $latestFirst);

        $rows = $this->withSeated($rows, $studentId);
        // The learner's own seat in each group (by when they joined: "seat 4 of 5").
        $groupCourses = array_values(array_unique(array_filter(array_map(fn (object $r) => $r->group_id === null ? null : $r->enrollment_id, $rows))));
        $seats = Groups\CourseGroupsQuery::read($groupCourses, app(CourseDays::class)->today()->toDateString());
        foreach ($rows as $r) {
            $r->seat_number = $r->group_id === null ? null : ($seats[$r->enrollment_id]['seat'] ?? null);
        }

        return $rows;
    }

    /** @return list<object> the same, a group lesson once with `roster` (each student seated: id, name) */
    public function forTeacher(string $teacherId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->rows(fn (Builder $q) => $q->where('s.teacher_id', $teacherId)
            ->leftJoinLateral(DB::table('session_participants as p')
                ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'p.academy_id')->on('u.id', '=', 'p.user_id'))
                ->whereColumn('p.academy_id', 's.academy_id')->whereColumn('p.session_id', 's.id')->where('p.role', 'student')
                ->orderByDesc('p.is_active')->orderBy('u.name')->limit(1)->select(['u.name']), 'su')
            // A teacher's dispute: one about any seat, an open one first.
            ->leftJoinLateral(DB::table('session_disputes as d')->whereColumn('d.academy_id', 's.academy_id')->whereColumn('d.session_id', 's.id')->where('d.side', 'teacher')
                ->orderByRaw("(d.status = 'open') desc")->orderBy('d.created_at')->limit(1)->select(['d.status', 'd.side', 'd.reason', 'd.decision_note']), 'd')
            ->addSelect(['su.name as student', DB::raw('null as seat_outcome'), DB::raw('true as seat_active'), 's.enrollment_id']),
            $from, $to);

        return $this->withSeated($rows, null);
    }

    /** @return list<object> */
    private function rows(callable $who, CarbonImmutable $from, CarbonImmutable $to, bool $latestFirst = false): array
    {
        return DB::table('sessions as s')
            ->join('users as t', fn ($j) => $j->on('t.academy_id', '=', 's.academy_id')->on('t.id', '=', 's.teacher_id'))
            ->join('subjects as sub', fn ($j) => $j->on('sub.academy_id', '=', 's.academy_id')->on('sub.id', '=', 's.subject_id'))
            ->leftJoin('users as ot', fn ($j) => $j->on('ot.academy_id', '=', 's.academy_id')->on('ot.id', '=', 's.original_teacher_id'))
            ->leftJoin('lesson_groups as g', fn ($j) => $j->on('g.academy_id', '=', 's.academy_id')->on('g.id', '=', 's.group_id'))
            ->select(['s.id', 's.starts_at', 's.ends_at', 's.status', 's.type', 's.funding_status', 't.name as teacher', 'sub.name_ar as subject_ar', 'sub.name_en as subject_en',
                'ot.name as original_teacher', DB::raw("s.decision->>'reason' as cancel_reason"),
                'd.status as dispute_status', 'd.side as dispute_side', 'd.reason as dispute_reason', 'd.decision_note as dispute_answer',
                's.group_id', 'g.capacity',
                DB::raw("(select count(*) from session_participants c where c.academy_id = s.academy_id and c.session_id = s.id and c.role = 'student' and c.is_active) as students")])
            ->tap($who)
            ->where('s.starts_at', '>=', $from)->where('s.starts_at', '<', $to)
            ->orderBy('s.starts_at', $latestFirst ? 'desc' : 'asc')
            ->get()
            ->map(function (object $r) {
                $r->starts_at = CarbonImmutable::parse($r->starts_at)->utc();
                $r->ends_at = CarbonImmutable::parse($r->ends_at)->utc();
                $r->students = (int) $r->students;
                $r->capacity = $r->capacity === null ? null : (int) $r->capacity;
                $r->seat_active = (bool) $r->seat_active;

                return $r;
            })->all();
    }

    /**
     * A group lesson's other students: for a student their classmates' first names only (never a name in full, an
     * email or a number), for the teacher everyone seated by name.
     *
     * @param  list<object>  $rows
     * @return list<object>
     */
    private function withSeated(array $rows, ?string $studentId): array
    {
        $groups = array_values(array_filter(array_map(fn (object $r) => $r->group_id === null ? null : $r->id, $rows)));
        $seated = $groups === [] ? collect() : DB::table('session_participants as p')
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'p.academy_id')->on('u.id', '=', 'p.user_id'))
            ->whereIn('p.session_id', $groups)->where('p.role', 'student')->where('p.is_active', true)
            ->orderBy('u.name')->get(['p.session_id', 'p.user_id', 'u.name'])->groupBy('session_id');

        foreach ($rows as $r) {
            $people = $r->group_id === null ? collect() : ($seated[$r->id] ?? collect());
            $r->classmates = $studentId === null ? [] : $people->where('user_id', '!=', $studentId)
                ->map(fn (object $p) => AttendanceService::firstName((string) $p->name))->values()->all();
            $r->roster = $studentId !== null ? [] : $people->map(fn (object $p) => ['id' => $p->user_id, 'name' => (string) $p->name])->values()->all();
        }

        return $rows;
    }
}
