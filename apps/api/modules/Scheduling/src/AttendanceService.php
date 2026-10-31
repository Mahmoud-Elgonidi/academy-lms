<?php

namespace Modules\Scheduling;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Actions\RecordConnectionDrop;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Actions\RecordParticipantLeave;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\DTOs\LearnerLesson;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use Modules\Scheduling\Domain\Models\Session;

final class AttendanceService implements AttendanceApi
{
    public function __construct(
        private readonly RecordParticipantJoin $join,
        private readonly RecordParticipantLeave $leave,
        private readonly RecordConnectionDrop $drop,
    ) {}

    public function lesson(string $sessionId): ?LessonCard
    {
        return $this->cards(fn ($q) => $q->where('s.id', $sessionId))[0] ?? null;
    }

    public function lessonsOf(string $teacherId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->cards(fn ($q) => $q->where('s.teacher_id', $teacherId)->where('s.starts_at', '>=', $from)->where('s.starts_at', '<', $to));
    }

    public function lessonsOfStudent(string $studentId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DB::table('sessions as s')
            ->join('session_participants as p', fn ($j) => $j->on('p.academy_id', '=', 's.academy_id')->on('p.session_id', '=', 's.id')
                ->where('p.role', 'student')->where('p.user_id', $studentId))
            ->where('s.starts_at', '>=', $from)->where('s.starts_at', '<', $to)
            ->orderBy('s.starts_at')
            // A group lesson is, for its student, what their own seat came to (attended, absent, excused, a makeup owed).
            ->get(['s.id', DB::raw("case when s.group_id is not null and p.outcome is not null then (case p.outcome when 'attended' then 'completed' when 'absent' then 'student_absent' when 'excused' then 'cancelled' else 'teacher_absent' end) else s.status end as status"), 's.starts_at', 's.ends_at', 'p.first_join_at', 'p.last_left_at', 'p.attended_min', DB::raw('coalesce(s.enrollment_id, p.enrollment_id) as enrollment_id')])
            ->map(fn (object $r) => new LearnerLesson(
                $r->id, $r->status, CarbonImmutable::parse($r->starts_at),
                $r->first_join_at === null ? null : CarbonImmutable::parse($r->first_join_at), $r->enrollment_id,
                CarbonImmutable::parse($r->ends_at), $r->last_left_at === null ? null : CarbonImmutable::parse($r->last_left_at), (int) $r->attended_min,
            ))->all();
    }

    public function lessonsInClass(CarbonImmutable $at, int $beforeMin, int $afterMin): array
    {
        return DB::table('sessions')->whereIn('status', ['scheduled', 'in_progress'])
            ->where('starts_at', '<=', $at->addMinutes($beforeMin))
            ->where('ends_at', '>=', $at->subMinutes($afterMin))
            ->pluck('id')->all();
    }

    public function inClassroom(string $sessionId): array
    {
        return DB::table('attendance_segments')->where('session_id', $sessionId)->whereNull('left_at')->pluck('user_id')->all();
    }

    public function participantJoined(string $sessionId, string $userId, CarbonImmutable $at, string $source = 'provider'): void
    {
        $this->join->handle(Session::query()->findOrFail($sessionId), $userId, $at, $source);
    }

    public function participantLeft(string $sessionId, string $userId, CarbonImmutable $at, string $source = 'provider'): void
    {
        $this->leave->handle(Session::query()->findOrFail($sessionId), $userId, $at, $source);
    }

    public function connectionDropped(string $sessionId, string $userId, int $seconds, CarbonImmutable $at): void
    {
        $this->drop->handle(Session::query()->findOrFail($sessionId), $userId, $seconds, $at);
    }

    /** @return list<LessonCard> one per lesson, a group lesson's with every student seated */
    private function cards(callable $where): array
    {
        $query = DB::table('sessions as s')
            ->join('users as t', fn ($j) => $j->on('t.academy_id', '=', 's.academy_id')->on('t.id', '=', 's.teacher_id'))
            ->join('subjects as su', fn ($j) => $j->on('su.academy_id', '=', 's.academy_id')->on('su.id', '=', 's.subject_id'))
            ->leftJoin('lesson_groups as g', fn ($j) => $j->on('g.academy_id', '=', 's.academy_id')->on('g.id', '=', 's.group_id'))
            ->orderBy('s.starts_at')
            ->select('s.id', 's.status', 's.starts_at', 's.ends_at', 's.teacher_id', 't.name as teacher', 'su.name_ar', 'su.name_en', 's.enrollment_id', 's.group_id', 'g.capacity');
        $where($query);
        $rows = $query->get();
        $students = $rows->isEmpty() ? collect() : DB::table('session_participants as p')
            ->join('users as st', fn ($j) => $j->on('st.academy_id', '=', 'p.academy_id')->on('st.id', '=', 'p.user_id'))
            ->whereIn('p.session_id', $rows->pluck('id')->all())->where('p.role', 'student')->where('p.is_active', true)
            ->orderBy('st.name')->orderBy('p.user_id')
            ->get(['p.session_id', 'p.user_id', 'st.name'])->groupBy('session_id');

        return $rows->map(function (object $r) use ($students) {
            $seated = ($students[$r->id] ?? collect())->map(fn (object $p) => ['id' => $p->user_id, 'name' => (string) $p->name, 'first_name' => self::firstName((string) $p->name)])->values()->all();

            return new LessonCard(
                $r->id, $r->status, CarbonImmutable::parse($r->starts_at), CarbonImmutable::parse($r->ends_at),
                $r->teacher_id, $r->teacher, $seated[0]['id'] ?? null, (string) ($seated[0]['name'] ?? ''), $r->name_ar, $r->name_en, $r->enrollment_id,
                $seated, $r->group_id, $r->capacity === null ? null : (int) $r->capacity,
            );
        })->all();
    }

    /** What a classmate sees of a student: the first word of their name. */
    public static function firstName(string $name): string
    {
        return (string) (preg_split('/\s+/u', trim($name))[0] ?? '');
    }
}
