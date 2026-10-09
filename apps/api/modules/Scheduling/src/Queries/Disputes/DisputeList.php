<?php

namespace Modules\Scheduling\Queries\Disputes;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Disputes for the back office (docs/architecture/01, section 8): the ones
 * waiting, oldest first, and those decided lately, with the lesson, who
 * said what, and how long each side was in the classroom during the
 * lesson's time (from attendance_segments, read each time).
 */
final class DisputeList
{
    public static function waiting(): int
    {
        return DB::table('session_disputes')->where('status', 'open')->count();
    }

    /** @return list<object> */
    public function open(): array
    {
        return $this->rows($this->base()->where('d.status', 'open')->orderBy('d.created_at'));
    }

    /** @return list<object> decided in the last $days days, latest first */
    public function decided(int $days = 30): array
    {
        return $this->rows($this->base()->where('d.status', '<>', 'open')->where('d.decided_at', '>=', CarbonImmutable::now()->subDays($days))->orderByDesc('d.decided_at')->limit(100));
    }

    public function find(string $id): ?object
    {
        return $this->rows($this->base()->where('d.id', $id))[0] ?? null;
    }

    private function base(): Builder
    {
        $user = fn (string $alias, string $column) => fn ($j) => $j->on("{$alias}.academy_id", '=', 'd.academy_id')->on("{$alias}.id", '=', $column);
        // Seconds someone of this role was in the room inside the lesson's own time, and when they first came in.
        $present = fn (string $role) => DB::raw("(select coalesce(sum(extract(epoch from upper(x) - lower(x))), 0) from unnest(
                (select range_agg(tstzrange(a.joined_at, coalesce(a.left_at, s.ends_at), '[)')) from attendance_segments a
                  where a.academy_id = s.academy_id and a.session_id = s.id and a.role = '{$role}')
                * tstzmultirange(tstzrange(s.starts_at, s.ends_at, '[)'))) as x) as {$role}_seconds");
        $first = fn (string $role) => DB::raw("(select min(a.joined_at) from attendance_segments a
                  where a.academy_id = s.academy_id and a.session_id = s.id and a.role = '{$role}') as {$role}_first");

        return DB::table('session_disputes as d')
            ->join('sessions as s', fn ($j) => $j->on('s.academy_id', '=', 'd.academy_id')->on('s.id', '=', 'd.session_id'))
            ->join('subjects as sub', fn ($j) => $j->on('sub.academy_id', '=', 's.academy_id')->on('sub.id', '=', 's.subject_id'))
            ->join('users as t', $user('t', 's.teacher_id'))
            ->join('users as r', $user('r', 'd.raised_by'))
            ->leftJoin('users as dc', $user('dc', 'd.decided_by'))
            ->leftJoinLateral(DB::table('session_participants as p')
                ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'p.academy_id')->on('u.id', '=', 'p.user_id'))
                ->whereColumn('p.academy_id', 's.academy_id')->whereColumn('p.session_id', 's.id')->where('p.role', 'student')
                ->limit(1)->select(['u.id as student_id', 'u.name as student']), 'st')
            ->select([
                'd.id', 'd.session_id', 'd.side', 'd.reason', 'd.note', 'd.status', 'd.outcome_before', 'd.outcome_after', 'd.decision_note', 'd.created_at', 'd.decided_at',
                's.starts_at', 's.ends_at', 's.status as lesson_status', 's.type', 's.teacher_id', DB::raw("(s.decision->>'credit_short')::int as credit_short"),
                't.name as teacher', 'st.student_id', 'st.student', 'r.name as raised_by_name', 'dc.name as decided_by_name', 'sub.name_ar as subject_ar', 'sub.name_en as subject_en',
                $present('teacher'), $present('student'), $first('teacher'), $first('student'),
            ]);
    }

    /** @return list<object> */
    private function rows(Builder $query): array
    {
        return $query->get()->map(function (object $r) {
            foreach (['starts_at', 'ends_at', 'created_at', 'decided_at', 'teacher_first', 'student_first'] as $k) {
                $r->{$k} = $r->{$k} === null ? null : CarbonImmutable::parse($r->{$k})->utc();
            }
            $r->teacher_minutes = intdiv((int) round((float) $r->teacher_seconds), 60);
            $r->student_minutes = intdiv((int) round((float) $r->student_seconds), 60);

            return $r;
        })->all();
    }
}
