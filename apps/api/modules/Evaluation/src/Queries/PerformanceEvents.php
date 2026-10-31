<?php

namespace Modules\Evaluation\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What moved a teacher's numbers lately (docs/architecture/01, section 12,
 * "أحداث الأداء"): late joins, absences, cancellations inside the notice,
 * leaving early, reports in late or not yet in, and low ratings. Each with
 * its lesson, and whether a manager took the lesson out of the numbers.
 */
final class PerformanceEvents
{
    /** @return list<array{session_id: string, at: CarbonImmutable, kind: string, minutes: ?int, student: ?string, subject_ar: ?string, subject_en: ?string, stars: ?int, comment: ?string, excluded: ?object}> */
    public function forTeacher(string $teacherId, int $days = 30, ?CarbonImmutable $now = null): array
    {
        $now = ($now ?? CarbonImmutable::now())->utc();
        $from = $now->subDays($days);
        $late = (int) (json_decode((string) DB::table('policy_versions')->orderByDesc('version')->value('settings'), true)['teacher_late_after_min'] ?? 2);
        $lesson = fn () => DB::table('sessions as s')
            // One row a lesson: a group lesson as its first student and how many more.
            ->leftJoinLateral(DB::table('session_participants as sp')
                ->join('users as su', fn ($j) => $j->on('su.academy_id', '=', 'sp.academy_id')->on('su.id', '=', 'sp.user_id'))
                ->whereColumn('sp.academy_id', 's.academy_id')->whereColumn('sp.session_id', 's.id')->where('sp.role', 'student')
                ->selectRaw("min(su.name) || case when count(*) > 1 then ' +' || (count(*) - 1) else '' end as name"), 'u')
            ->leftJoin('subjects as sub', fn ($j) => $j->on('sub.academy_id', '=', 's.academy_id')->on('sub.id', '=', 's.subject_id'))
            ->where('s.teacher_id', $teacherId)->where('s.starts_at', '>=', $from)->where('s.starts_at', '<', $now);
        $cols = ['s.id as session_id', 's.starts_at as at', 'u.name as student', 'sub.name_ar as subject_ar', 'sub.name_en as subject_en'];

        $rows = collect()
            ->merge($lesson()->join('session_participants as tp', fn ($j) => $j->on('tp.academy_id', '=', 's.academy_id')->on('tp.session_id', '=', 's.id')->where('tp.role', 'teacher')->whereColumn('tp.user_id', 's.teacher_id'))
                ->whereIn('s.status', ['completed', 'student_absent'])->whereRaw('tp.first_join_at > s.starts_at + make_interval(mins => ?)', [$late])
                ->selectRaw("'late_join' as kind, round(extract(epoch from tp.first_join_at - s.starts_at) / 60)::int as minutes")->addSelect($cols)->get())
            ->merge($lesson()->where('s.status', 'teacher_absent')->selectRaw("'absent' as kind, null::int as minutes")->addSelect($cols)->get())
            ->merge($lesson()->where('s.status', 'cancelled')->whereRaw("s.decision->>'by' = 'teacher' and s.decision->>'rule' = '8'")
                ->selectRaw("'late_cancel' as kind, null::int as minutes")->addSelect($cols)->get())
            ->merge($lesson()->join('session_participants as tp', fn ($j) => $j->on('tp.academy_id', '=', 's.academy_id')->on('tp.session_id', '=', 's.id')->where('tp.role', 'teacher')->whereColumn('tp.user_id', 's.teacher_id'))
                ->where('s.status', 'completed')->whereRaw('tp.last_left_at < s.ends_at - make_interval(mins => ?)', [TeacherKpis::EARLY_LEAVE_MIN])
                ->selectRaw("'early_leave' as kind, round(extract(epoch from s.ends_at - tp.last_left_at) / 60)::int as minutes")->addSelect($cols)->get())
            // A lesson's reports (one per student in a group lesson): missing when any is, else late when any was.
            ->merge($lesson()->whereExists(fn ($q) => $q->from('session_reports as r')->whereColumn('r.academy_id', 's.academy_id')->whereColumn('r.session_id', 's.id')
                ->where('r.kind', 'lesson')->where(fn ($q) => $q->where('r.late', true)->orWhere(fn ($q) => $q->whereNull('r.submitted_at')->where('r.due_at', '<', $now))))
                ->selectRaw("case when exists (select 1 from session_reports r where r.academy_id = s.academy_id and r.session_id = s.id and r.kind = 'lesson' and r.submitted_at is null and r.due_at < ?)
                    then 'report_missing' else 'report_late' end as kind, null::int as minutes", [$now])->addSelect($cols)->get());

        $low = DB::table('lesson_ratings as g')
            ->join('sessions as s', fn ($j) => $j->on('s.academy_id', '=', 'g.academy_id')->on('s.id', '=', 'g.session_id'))
            ->leftJoin('users as u', fn ($j) => $j->on('u.academy_id', '=', 'g.academy_id')->on('u.id', '=', 'g.student_id'))
            ->leftJoin('subjects as sub', fn ($j) => $j->on('sub.academy_id', '=', 's.academy_id')->on('sub.id', '=', 's.subject_id'))
            ->where('g.teacher_id', $teacherId)->where('g.stars', '<=', 3)->where('g.created_at', '>=', $from)
            ->get(['g.session_id', 's.starts_at as at', 'u.name as student', 'sub.name_ar as subject_ar', 'sub.name_en as subject_en', 'g.stars', 'g.comment'])
            ->map(fn ($r) => (object) ((array) $r + ['kind' => 'low_rating', 'minutes' => null]));

        $all = $rows->merge($low);
        $excluded = DB::table('kpi_exclusions as x')->leftJoin('users as m', fn ($j) => $j->on('m.academy_id', '=', 'x.academy_id')->on('m.id', '=', 'x.excluded_by'))
            ->whereIn('x.session_id', $all->pluck('session_id')->unique()->values())
            ->get(['x.session_id', 'x.reason', 'x.note', 'm.name as by'])->keyBy('session_id');

        return $all->map(fn ($r) => [
            'session_id' => $r->session_id,
            'at' => CarbonImmutable::parse($r->at),
            'kind' => $r->kind,
            'minutes' => $r->minutes !== null ? (int) $r->minutes : null,
            'student' => $r->student,
            'subject_ar' => $r->subject_ar,
            'subject_en' => $r->subject_en,
            'stars' => isset($r->stars) ? (int) $r->stars : null,
            'comment' => $r->comment ?? null,
            'excluded' => $excluded[$r->session_id] ?? null,
        ])->sortByDesc(fn (array $e) => $e['at']->getTimestamp())->values()->all();
    }

    /** @return Collection<int, object> the teacher's latest ratings, newest first */
    public function ratings(string $teacherId, int $limit = 20): Collection
    {
        return DB::table('lesson_ratings as g')
            ->join('sessions as s', fn ($j) => $j->on('s.academy_id', '=', 'g.academy_id')->on('s.id', '=', 'g.session_id'))
            ->leftJoin('users as u', fn ($j) => $j->on('u.academy_id', '=', 'g.academy_id')->on('u.id', '=', 'g.student_id'))
            ->leftJoin('users as r', fn ($j) => $j->on('r.academy_id', '=', 'g.academy_id')->on('r.id', '=', 'g.rater_id'))
            ->leftJoin('subjects as sub', fn ($j) => $j->on('sub.academy_id', '=', 's.academy_id')->on('sub.id', '=', 's.subject_id'))
            ->where('g.teacher_id', $teacherId)->orderByDesc('g.updated_at')->limit($limit)
            ->get(['g.id', 'g.session_id', 'g.stars', 'g.tags', 'g.comment', 'g.updated_at', 's.starts_at', 'u.name as student', 'r.name as rater', 'sub.name_ar as subject_ar', 'sub.name_en as subject_en']);
    }
}
