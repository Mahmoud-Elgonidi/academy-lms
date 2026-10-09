<?php

namespace Modules\Scheduling\Queries\Calendar;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Queries\AvailabilityQuery;

/**
 * The academy's lessons over a range of days (docs/architecture/01, sections
 * 7 and 17): each lesson with who, what and where it stands, and the same
 * lessons counted by status, by day on the viewer's clock, by teacher and by
 * subject. A lesson belongs to the day it starts. The counts are read in SQL
 * each time; nothing is kept. Joins users and catalog directly (reads may).
 */
final class LessonsCalendar
{
    /** The most lessons one list shows; the spreadsheet has them all. */
    public const LIST_LIMIT = 500;

    public function __construct(private readonly AvailabilityQuery $availability) {}

    /** @return list<string> every status, in the order a lesson moves through them */
    public static function statuses(): array
    {
        return array_map(fn (SessionStatus $s) => $s->value, SessionStatus::cases());
    }

    /**
     * Lessons starting in [from, to), earliest first.
     *
     * @return list<object> rows with id, starts_at, ends_at (UTC), minutes, status, type, funding_status, review_reason, reason, reason_by, reason_note (a cancellation's),
     *                      enrollment_id, teacher_id, teacher, teacher_tz, original_teacher, student_id, student, student_tz, subject_id, subject_ar, subject_en
     */
    public function lessons(CalendarFilter $filter, CarbonImmutable $from, CarbonImmutable $to, ?int $limit = null): array
    {
        return $this->rows($this->base($filter, $from, $to)->orderBy('s.starts_at')->orderBy('t.name')->when($limit, fn (Builder $q, int $n) => $q->limit($n)));
    }

    /** One lesson, whatever the range (the details panel). */
    public function lesson(string $id): ?object
    {
        return $this->rows(DB::table('sessions as s')->where('s.id', $id))[0] ?? null;
    }

    /** @return array<string, array{count: int, minutes: int}> every status, in order, with how many lessons and their minutes */
    public function byStatus(CalendarFilter $filter, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->base($filter, $from, $to)
            ->groupBy('s.status')
            ->selectRaw('s.status, count(*) as n, coalesce(sum(extract(epoch from s.ends_at - s.starts_at)), 0) / 60 as minutes')
            ->get()->keyBy('status');

        return array_combine(self::statuses(), array_map(fn (string $s) => [
            'count' => (int) ($rows[$s]->n ?? 0), 'minutes' => (int) round((float) ($rows[$s]->minutes ?? 0)),
        ], self::statuses()));
    }

    /** @return array<string, array<string, int>> 'Y-m-d' on the clock given => status => lessons */
    public function byDay(CalendarFilter $filter, CarbonImmutable $from, CarbonImmutable $to, string $timezone): array
    {
        $days = [];
        $this->base($filter, $from, $to)
            ->selectRaw("to_char(s.starts_at at time zone ?, 'YYYY-MM-DD') as day, s.status, count(*) as n", [$timezone])
            ->groupByRaw('1, 2')
            ->get()
            ->each(function (object $r) use (&$days) {
                $days[$r->day][$r->status] = (int) $r->n;
            });

        return $days;
    }

    /** @return list<array{id: string, name: string, statuses: array<string, int>, total: int, minutes: int}> teachers by name; minutes of the lessons not cancelled */
    public function byTeacher(CalendarFilter $filter, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->pivot($this->base($filter, $from, $to)
            ->join('users as t', fn ($j) => $j->on('t.academy_id', '=', 's.academy_id')->on('t.id', '=', 's.teacher_id'))
            ->groupBy('s.teacher_id', 't.name', 's.status')
            ->selectRaw('s.teacher_id as id, t.name')
            ->orderBy('t.name'));
    }

    /** @return list<array{id: string, name: string, statuses: array<string, int>, total: int, minutes: int}> subjects by name, in the current language */
    public function bySubject(CalendarFilter $filter, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $name = app()->getLocale() === 'ar' ? 'sub.name_ar' : 'sub.name_en';

        return $this->pivot($this->base($filter, $from, $to)
            ->join('subjects as sub', fn ($j) => $j->on('sub.academy_id', '=', 's.academy_id')->on('sub.id', '=', 's.subject_id'))
            ->groupBy('s.subject_id', $name, 's.status')
            ->selectRaw("s.subject_id as id, {$name} as name")
            ->orderBy($name));
    }

    /**
     * Teachers' working time over [from, to): their shift hours (less holidays
     * and their time off, as booking sees them) and their approved time off.
     *
     * @param  list<string>  $teacherIds
     * @return array<string, array{shifts: list<array{0: int, 1: int}>, off: list<array{0: int, 1: int, 2: string}>}> Unix seconds; off carries the reason
     */
    public function working(array $teacherIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $result = array_fill_keys($teacherIds, ['shifts' => [], 'off' => []]);
        foreach ($this->availability->forTeachers($teacherIds, $from, $to) as $id => $availability) {
            $result[$id]['shifts'] = $availability->shifts->intervals;
        }
        DB::table('teacher_time_off')->whereIn('teacher_id', $teacherIds)->where('status', 'approved')
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)->orderBy('starts_at')
            ->get(['teacher_id', 'starts_at', 'ends_at', 'reason'])
            ->each(function (object $o) use (&$result) {
                $result[$o->teacher_id]['off'][] = [CarbonImmutable::parse($o->starts_at)->getTimestamp(), CarbonImmutable::parse($o->ends_at)->getTimestamp(), (string) $o->reason];
            });

        return $result;
    }

    /** @return list<array{name: string, from: int, to: int}> the academy's holidays over [from, to), Unix seconds */
    public function holidays(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DB::table('academy_holidays')->where('starts_at', '<', $to)->where('ends_at', '>', $from)->orderBy('starts_at')
            ->get(['name', 'starts_at', 'ends_at'])
            ->map(fn (object $h) => ['name' => (string) $h->name, 'from' => CarbonImmutable::parse($h->starts_at)->getTimestamp(), 'to' => CarbonImmutable::parse($h->ends_at)->getTimestamp()])
            ->all();
    }

    /** @return array<string, string> the academy's teachers, id => name */
    public function teachers(): array
    {
        return $this->people('teacher')->pluck('u.name', 'u.id')->all();
    }

    /** @return array<string, string> students whose name has the text (all when empty), id => name, at most 50 */
    public function students(?string $search = null): array
    {
        return $this->people('student')
            ->when(trim((string) $search) !== '', fn (Builder $q) => $q->where('u.name', 'ilike', '%'.addcslashes(trim((string) $search), '%_\\').'%'))
            ->limit(50)->pluck('u.name', 'u.id')->all();
    }

    /** @return array<string, string> the academy's subjects, id => name in the current language */
    public function subjects(): array
    {
        $name = app()->getLocale() === 'ar' ? 'name_ar' : 'name_en';

        return DB::table('subjects')->orderBy($name)->pluck($name, 'id')->all();
    }

    public function name(?string $userId): ?string
    {
        return $userId === null ? null : DB::table('users')->where('id', $userId)->value('name');
    }

    private function base(CalendarFilter $filter, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('sessions as s')
            ->where('s.starts_at', '>=', $from)->where('s.starts_at', '<', $to)
            ->when($filter->teacherId, fn (Builder $q, string $id) => $q->where('s.teacher_id', $id))
            ->when($filter->subjectId, fn (Builder $q, string $id) => $q->where('s.subject_id', $id))
            ->when($filter->type, fn (Builder $q, string $type) => $q->where('s.type', $type))
            ->when($filter->statuses, fn (Builder $q, array $statuses) => $q->whereIn('s.status', $statuses))
            ->when($filter->studentId, fn (Builder $q, string $id) => $q->whereExists(fn (Builder $e) => $e->selectRaw('1')->from('session_participants as p')
                ->whereColumn('p.academy_id', 's.academy_id')->whereColumn('p.session_id', 's.id')->where('p.role', 'student')->where('p.user_id', $id)));
    }

    /** @return list<object> */
    private function rows(Builder $query): array
    {
        // A lesson's learner (one: lessons are one to one; the first by id if a group ever comes).
        $learner = DB::table('session_participants as p')->whereColumn('p.academy_id', 's.academy_id')->whereColumn('p.session_id', 's.id')
            ->where('p.role', 'student')->orderBy('p.user_id')->limit(1)->select('p.user_id');

        return $query
            ->leftJoinLateral($learner, 'st')
            ->leftJoin('users as su', fn ($j) => $j->on('su.academy_id', '=', 's.academy_id')->on('su.id', '=', 'st.user_id'))
            ->join('users as t', fn ($j) => $j->on('t.academy_id', '=', 's.academy_id')->on('t.id', '=', 's.teacher_id'))
            ->join('subjects as sub', fn ($j) => $j->on('sub.academy_id', '=', 's.academy_id')->on('sub.id', '=', 's.subject_id'))
            ->leftJoin('users as ot', fn ($j) => $j->on('ot.academy_id', '=', 's.academy_id')->on('ot.id', '=', 's.original_teacher_id'))
            ->leftJoin('session_disputes as d', fn ($j) => $j->on('d.academy_id', '=', 's.academy_id')->on('d.session_id', '=', 's.id'))
            ->get(['s.id', 's.starts_at', 's.ends_at', 's.status', 's.type', 's.funding_status', 's.review_reason', 's.enrollment_id', 's.subject_id',
                's.teacher_id', 't.name as teacher', 't.timezone as teacher_tz', 'ot.name as original_teacher',
                'st.user_id as student_id', 'su.name as student', 'su.timezone as student_tz',
                'sub.name_ar as subject_ar', 'sub.name_en as subject_en', 'd.id as dispute_id', 'd.status as dispute_status', 'd.side as dispute_side',
                DB::raw("s.decision->>'reason' as reason"), DB::raw("s.decision->>'by' as reason_by"), DB::raw("s.decision->>'note' as reason_note")])
            ->map(function (object $r) {
                $r->starts_at = CarbonImmutable::parse($r->starts_at)->utc();
                $r->ends_at = CarbonImmutable::parse($r->ends_at)->utc();
                $r->minutes = (int) $r->starts_at->diffInMinutes($r->ends_at);

                return $r;
            })->all();
    }

    /** @return list<array{id: string, name: string, statuses: array<string, int>, total: int, minutes: int}> */
    private function pivot(Builder $query): array
    {
        $out = [];
        $query->addSelect('s.status')
            ->selectRaw('count(*) as n')
            ->selectRaw("coalesce(sum(extract(epoch from s.ends_at - s.starts_at)) filter (where s.status <> 'cancelled'), 0) / 60 as minutes")
            ->get()
            ->each(function (object $r) use (&$out) {
                $out[$r->id] ??= ['id' => (string) $r->id, 'name' => (string) $r->name, 'statuses' => array_fill_keys(self::statuses(), 0), 'total' => 0, 'minutes' => 0];
                $out[$r->id]['statuses'][$r->status] = (int) $r->n;
                $out[$r->id]['total'] += (int) $r->n;
                $out[$r->id]['minutes'] += (int) round((float) $r->minutes);
            });

        return array_values($out);
    }

    private function people(string $role): Builder
    {
        return DB::table('users as u')
            ->whereExists(fn (Builder $e) => $e->selectRaw('1')->from('user_roles as r')->whereColumn('r.academy_id', 'u.academy_id')->whereColumn('r.user_id', 'u.id')->where('r.role', $role))
            ->orderBy('u.name');
    }
}
