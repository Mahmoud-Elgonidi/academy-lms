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
            ->get(['s.id', 's.status', 's.starts_at', 's.ends_at', 'p.first_join_at', 'p.last_left_at', 'p.attended_min', 's.enrollment_id'])
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

    /** @return list<LessonCard> */
    private function cards(callable $where): array
    {
        $query = DB::table('sessions as s')
            ->join('users as t', fn ($j) => $j->on('t.academy_id', '=', 's.academy_id')->on('t.id', '=', 's.teacher_id'))
            ->join('subjects as su', fn ($j) => $j->on('su.academy_id', '=', 's.academy_id')->on('su.id', '=', 's.subject_id'))
            ->leftJoin('session_participants as p', fn ($j) => $j->on('p.academy_id', '=', 's.academy_id')->on('p.session_id', '=', 's.id')
                ->where('p.role', 'student')->where('p.is_active', true))
            ->leftJoin('users as st', fn ($j) => $j->on('st.academy_id', '=', 'p.academy_id')->on('st.id', '=', 'p.user_id'))
            ->orderBy('s.starts_at')
            ->select('s.id', 's.status', 's.starts_at', 's.ends_at', 's.teacher_id', 't.name as teacher', 'p.user_id as student_id', 'st.name as student', 'su.name_ar', 'su.name_en', 's.enrollment_id');
        $where($query);

        return $query->get()->map(fn (object $r) => new LessonCard(
            $r->id, $r->status, CarbonImmutable::parse($r->starts_at), CarbonImmutable::parse($r->ends_at),
            $r->teacher_id, $r->teacher, $r->student_id, (string) $r->student, $r->name_ar, $r->name_en, $r->enrollment_id,
        ))->all();
    }
}
