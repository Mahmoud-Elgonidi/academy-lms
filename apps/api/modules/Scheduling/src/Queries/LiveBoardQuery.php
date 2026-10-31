<?php

namespace Modules\Scheduling\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Enums\SessionType;
use Modules\Scheduling\Domain\LiveBoard\BoardEvent;
use Modules\Scheduling\Domain\LiveBoard\BoardPerson;
use Modules\Scheduling\Domain\LiveBoard\BoardSession;

/**
 * Everything the live board shows, in four reads. Joins other modules' tables
 * directly (reads may; writes go through contracts). RLS limits every read to
 * the current academy.
 */
final class LiveBoardQuery
{
    public const LOOKBACK_HOURS = 4;

    /** @return list<BoardSession> */
    public function around(CarbonImmutable $now, int $aheadMinutes, string $fallbackTimezone): array
    {
        $sessions = DB::table('sessions as s')
            ->join('subjects as sub', fn ($j) => $j->on('sub.academy_id', '=', 's.academy_id')->on('sub.id', '=', 's.subject_id'))
            ->where(fn ($q) => $q
                ->whereBetween('s.starts_at', [$now->subHours(self::LOOKBACK_HOURS), $now->addMinutes($aheadMinutes)])
                ->orWhere(fn ($q) => $q->where('s.status', SessionStatus::NeedsReview->value)->where('s.starts_at', '>=', $now->subDay())))
            ->orderBy('s.starts_at')
            ->select('s.*', 'sub.name_ar as subject_ar', 'sub.name_en as subject_en')
            ->get();

        if ($sessions->isEmpty()) {
            return [];
        }

        $ids = $sessions->pluck('id')->all();

        $participants = DB::table('session_participants as p')
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'p.academy_id')->on('u.id', '=', 'p.user_id'))
            ->whereIn('p.session_id', $ids)
            ->orderByDesc('p.is_active')
            ->select('p.session_id', 'p.role', 'p.is_active', 'p.first_join_at', 'u.id', 'u.name', 'u.timezone', 'u.phone_e164')
            ->get()
            ->groupBy('session_id');

        $studentIds = $participants->flatten(1)->where('role', 'student')->pluck('id')->unique()->all();
        $guardians = DB::table('guardian_student as g')
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'g.academy_id')->on('u.id', '=', 'g.guardian_id'))
            ->whereIn('g.student_id', $studentIds)
            ->orderByDesc('g.is_primary')
            ->select('g.student_id', 'g.relationship', 'u.id', 'u.name', 'u.timezone', 'u.phone_e164')
            ->get()
            ->groupBy('student_id');

        $events = DB::table('session_events as e')
            ->leftJoin('users as u', fn ($j) => $j->on('u.academy_id', '=', 'e.academy_id')->on('u.id', '=', 'e.actor_id'))
            ->whereIn('e.session_id', $ids)
            ->orderBy('e.occurred_at')
            ->select('e.session_id', 'e.kind', 'e.data', 'e.occurred_at', 'u.name as actor_name')
            ->get()
            ->groupBy('session_id');

        return $sessions->map(function (object $row) use ($participants, $guardians, $events, $fallbackTimezone): BoardSession {
            /** @var Collection<int, object> $people */
            $people = $participants->get($row->id, collect());
            $teacher = $people->firstWhere('role', 'teacher');
            // A group lesson: every student seated in it; the one who came in first stands for them on the board.
            $seated = $row->group_id === null ? collect() : $people->where('role', 'student')->where('is_active', true)->sortBy('name')->values();
            $student = $row->group_id === null
                ? $people->firstWhere('role', 'student')
                : ($seated->filter(fn (object $p) => $p->first_join_at !== null)->sortBy('first_join_at')->first() ?? $seated->first());
            $family = $row->group_id === null ? collect([$student])->filter() : $seated;

            return new BoardSession(
                id: $row->id,
                type: SessionType::from($row->type),
                status: SessionStatus::from($row->status),
                funding: FundingStatus::from($row->funding_status),
                startsAt: self::time($row->starts_at),
                endsAt: self::time($row->ends_at),
                subjectAr: $row->subject_ar,
                subjectEn: $row->subject_en,
                teacher: self::person($teacher, $fallbackTimezone),
                student: $student ? self::person($student, $fallbackTimezone) : null,
                guardians: $family->flatMap(fn (object $st) => $guardians->get($st->id, collect()))->unique('id')
                    ->map(fn (object $g) => new BoardPerson($g->id, $g->name, $g->timezone ?? $fallbackTimezone, $g->phone_e164, null, $g->relationship))->values()->all(),
                events: $events->get($row->id, collect())->map(fn (object $e) => new BoardEvent(
                    SessionEventKind::from($e->kind),
                    self::time($e->occurred_at),
                    json_decode($e->data, true) ?: [],
                    $e->actor_name,
                ))->values()->all(),
                reviewReason: $row->review_reason,
                students: $seated->map(fn (object $p) => self::person($p, $fallbackTimezone))->all(),
                groupId: $row->group_id,
            );
        })->values()->all();
    }

    /** When the first open lesson after $after starts: the next one to come into view. */
    public function nextStartAfter(CarbonImmutable $after): ?CarbonImmutable
    {
        $at = DB::table('sessions')->where('starts_at', '>', $after)
            ->whereIn('status', [SessionStatus::Scheduled->value, SessionStatus::InProgress->value])
            ->min('starts_at');

        return $at === null ? null : self::time($at);
    }

    /**
     * Counts for the day [from, to) by status, for the board's headline numbers.
     *
     * @return array<string, int>
     */
    public function countsBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DB::table('sessions')
            ->whereBetween('starts_at', [$from, $to])
            ->groupBy('status')
            ->selectRaw('status, count(*) as n')
            ->pluck('n', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    private static function person(object $row, string $fallbackTimezone): BoardPerson
    {
        return new BoardPerson(
            id: $row->id,
            name: $row->name,
            timezone: $row->timezone ?? $fallbackTimezone,
            phone: $row->phone_e164,
            joinedAt: $row->first_join_at ? self::time($row->first_join_at) : null,
        );
    }

    private static function time(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value)->utc();
    }
}
