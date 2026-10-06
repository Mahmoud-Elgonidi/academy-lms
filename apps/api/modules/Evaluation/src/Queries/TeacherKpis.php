<?php

namespace Modules\Evaluation\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Evaluation\Domain\Kpi;
use Modules\Scheduling\Contracts\TrialConversionRates;

/**
 * Teacher KPIs over the last 90 days (docs/architecture/02, section 7),
 * read each time from the lessons, attendance, reports, homework and
 * ratings (reads may join across modules), so a dispute settled later is
 * counted as settled. A lesson a manager took out (kpi_exclusions) counts
 * for nothing. A substitute's lesson counts for the substitute.
 *
 *  punctuality  lessons the teacher joined within the late threshold ÷ lessons they taught
 *  avg_late     mean minutes late, over the late joins only
 *  absence      teacher absences ÷ lessons due (taught, absent, or cancelled by the teacher)
 *  late_cancel  the teacher's cancellations inside their notice (rule 8) ÷ lessons due
 *  taught       minutes taught ÷ minutes booked, over completed lessons
 *  early_leave  completed lessons the teacher left more than 3 minutes early ÷ completed
 *  rating       Bayesian average of the families' stars
 *  reports      lesson reports in on time ÷ reports due
 *  homework     hand-ins marked within 48 hours ÷ hand-ins due a mark
 *  trial_conversion  from Scheduling's contract (the CRM's numbers)
 */
final class TeacherKpis
{
    public const DAYS = 90;

    public const EARLY_LEAVE_MIN = 3;

    public const HOMEWORK_HOURS = 48;

    public function __construct(private readonly TrialConversionRates $conversion) {}

    /**
     * @param  list<string>  $teacherIds
     * @return array<string, array<string, array{value: ?float, n: int, meets: ?bool}>>
     */
    public function forTeachers(array $teacherIds, ?CarbonImmutable $now = null): array
    {
        if ($teacherIds === []) {
            return [];
        }
        $now = ($now ?? CarbonImmutable::now())->utc();
        $from = $now->subDays(self::DAYS);
        $late = $this->lateAfter();

        $lessons = DB::table('sessions as s')
            ->leftJoin('session_participants as tp', fn ($j) => $j->on('tp.academy_id', '=', 's.academy_id')->on('tp.session_id', '=', 's.id')->where('tp.role', 'teacher')->whereColumn('tp.user_id', 's.teacher_id'))
            ->leftJoin('session_participants as sp', fn ($j) => $j->on('sp.academy_id', '=', 's.academy_id')->on('sp.session_id', '=', 's.id')->where('sp.role', 'student'))
            ->whereIn('s.teacher_id', $teacherIds)->where('s.starts_at', '>=', $from)->where('s.starts_at', '<', $now)
            ->whereNotExists(fn ($q) => $q->from('kpi_exclusions as x')->whereColumn('x.session_id', 's.id')->whereColumn('x.academy_id', 's.academy_id'))
            ->groupBy('s.teacher_id')
            ->selectRaw("s.teacher_id,
                count(*) filter (where s.status in ('completed', 'student_absent')) as taught_lessons,
                count(*) filter (where s.status in ('completed', 'student_absent') and tp.first_join_at <= s.starts_at + make_interval(mins => ?)) as on_time,
                count(*) filter (where s.status in ('completed', 'student_absent') and tp.first_join_at > s.starts_at + make_interval(mins => ?)) as late_joins,
                avg(extract(epoch from tp.first_join_at - s.starts_at) / 60) filter (where s.status in ('completed', 'student_absent') and tp.first_join_at > s.starts_at + make_interval(mins => ?)) as avg_late,
                count(*) filter (where s.status = 'teacher_absent') as absent,
                count(*) filter (where s.status = 'cancelled' and s.decision->>'by' = 'teacher' and s.decision->>'rule' = '8') as late_cancel,
                count(*) filter (where s.status in ('completed', 'student_absent', 'teacher_absent') or (s.status = 'cancelled' and s.decision->>'by' = 'teacher')) as due,
                count(*) filter (where s.status = 'completed') as completed,
                count(*) filter (where s.status = 'completed' and tp.last_left_at < s.ends_at - make_interval(mins => ?)) as early_leave,
                sum(case when s.status = 'completed' then coalesce((s.decision->>'taught_min')::numeric, least(tp.attended_min, sp.attended_min), 0) end) as taught_min,
                sum(case when s.status = 'completed' then extract(epoch from s.ends_at - s.starts_at) / 60 end) as booked_min", [$late, $late, $late, self::EARLY_LEAVE_MIN])
            ->get()->keyBy('teacher_id');

        $reports = DB::table('session_reports as r')
            ->whereIn('r.teacher_id', $teacherIds)->where('r.kind', 'lesson')
            ->where('r.session_starts_at', '>=', $from)->where('r.session_starts_at', '<', $now)
            ->where(fn ($q) => $q->whereNotNull('r.submitted_at')->orWhere('r.due_at', '<', $now))
            ->whereNotExists(fn ($q) => $q->from('kpi_exclusions as x')->whereColumn('x.session_id', 'r.session_id')->whereColumn('x.academy_id', 'r.academy_id'))
            ->groupBy('r.teacher_id')
            ->selectRaw('r.teacher_id, count(*) as due, count(*) filter (where r.submitted_at is not null and not r.late) as on_time')
            ->get()->keyBy('teacher_id');

        $handIns = DB::table('homework as h')
            ->joinSub(DB::table('homework_submissions')->groupBy('homework_id', 'academy_id')->selectRaw('academy_id, homework_id, min(submitted_at) as first_at'), 'f',
                fn ($j) => $j->on('f.academy_id', '=', 'h.academy_id')->on('f.homework_id', '=', 'h.id'))
            ->whereIn('h.teacher_id', $teacherIds)->where('f.first_at', '>=', $from)
            ->whereRaw('(h.reviewed_at is not null or f.first_at < ?)', [$now->subHours(self::HOMEWORK_HOURS)])
            ->groupBy('h.teacher_id')
            ->selectRaw('h.teacher_id, count(*) as due, count(*) filter (where h.reviewed_at is not null and h.reviewed_at <= f.first_at + make_interval(hours => ?)) as on_time', [self::HOMEWORK_HOURS])
            ->get()->keyBy('teacher_id');

        $ratings = DB::table('lesson_ratings')->where('created_at', '>=', $from)
            ->whereNotExists(fn ($q) => $q->from('kpi_exclusions as x')->whereColumn('x.session_id', 'lesson_ratings.session_id')->whereColumn('x.academy_id', 'lesson_ratings.academy_id'));
        $mean = (float) ((clone $ratings)->avg('stars') ?? 4.5);
        $mine = (clone $ratings)->whereIn('teacher_id', $teacherIds)->groupBy('teacher_id')
            ->selectRaw('teacher_id, count(*) as n, sum(stars) as total')->get()->keyBy('teacher_id');

        $conversion = $this->conversion->forTeachers($teacherIds);

        $out = [];
        foreach ($teacherIds as $id) {
            $l = $lessons[$id] ?? null;
            $r = $reports[$id] ?? null;
            $h = $handIns[$id] ?? null;
            $g = $mine[$id] ?? null;
            $ratio = fn ($num, $den) => $den > 0 ? (float) $num / (float) $den : null;
            $values = [
                'punctuality' => [$ratio($l->on_time ?? 0, $l->taught_lessons ?? 0), (int) ($l->taught_lessons ?? 0)],
                'avg_late' => [isset($l->avg_late) ? round((float) $l->avg_late, 1) : null, (int) ($l->late_joins ?? 0)],
                'absence' => [$ratio($l->absent ?? 0, $l->due ?? 0), (int) ($l->due ?? 0)],
                'late_cancel' => [$ratio($l->late_cancel ?? 0, $l->due ?? 0), (int) ($l->due ?? 0)],
                'taught' => [$ratio(min((float) ($l->taught_min ?? 0), (float) ($l->booked_min ?? 0)), $l->booked_min ?? 0), (int) ($l->completed ?? 0)],
                'early_leave' => [$ratio($l->early_leave ?? 0, $l->completed ?? 0), (int) ($l->completed ?? 0)],
                'rating' => [Kpi::bayesian((int) ($g->n ?? 0), (int) ($g->total ?? 0), $mean), (int) ($g->n ?? 0)],
                'reports' => [$ratio($r->on_time ?? 0, $r->due ?? 0), (int) ($r->due ?? 0)],
                'homework' => [$ratio($h->on_time ?? 0, $h->due ?? 0), (int) ($h->due ?? 0)],
                'trial_conversion' => [$conversion[$id] ?? null, isset($conversion[$id]) ? 1 : 0],
            ];
            foreach (Kpi::all() as $kpi) {
                [$value, $n] = $values[$kpi->key];
                $out[$id][$kpi->key] = ['value' => $value, 'n' => $n, 'meets' => $kpi->meets($value, $n)];
            }
        }

        return $out;
    }

    /** The academy's "teacher counts as late after" minutes, from the policy in force. */
    private function lateAfter(): int
    {
        $settings = DB::table('policy_versions')->orderByDesc('version')->value('settings');

        return (int) (json_decode((string) $settings, true)['teacher_late_after_min'] ?? 2);
    }
}
