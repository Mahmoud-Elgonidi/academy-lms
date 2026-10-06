<?php

namespace Modules\Scheduling\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Shifts\ShiftBlock;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;

/**
 * Booked sessions that a new weekly schedule would leave outside the teacher's
 * shifts, from the date it starts. Weekly bookings are grouped into one
 * conflict per student, weekday and time, which is what someone resolves.
 * Sessions are never deleted because a shift changed.
 */
final class ShiftImpactQuery
{
    /**
     * @return list<array{key: string, student_id: string, student: string, student_tz: string, enrollment_id: ?string, subject_ar: string, subject_en: string,
     *     weekday: int, start: string, duration_min: int, count: int, first: CarbonImmutable, last: CarbonImmutable, session_ids: list<string>}>
     */
    public function conflicts(string $teacherId, WeeklyShifts $shifts, CarbonImmutable $effectiveFrom, string $fallbackTz = 'UTC'): array
    {
        $from = CarbonImmutable::parse($effectiveFrom->toDateString(), $shifts->timezone)->utc();

        $rows = DB::table('sessions as s')
            ->join('subjects as sub', fn ($j) => $j->on('sub.academy_id', '=', 's.academy_id')->on('sub.id', '=', 's.subject_id'))
            ->leftJoin('session_participants as p', fn ($j) => $j->on('p.academy_id', '=', 's.academy_id')->on('p.session_id', '=', 's.id')->where('p.role', 'student'))
            ->leftJoin('users as u', fn ($j) => $j->on('u.academy_id', '=', 'p.academy_id')->on('u.id', '=', 'p.user_id'))
            ->where('s.teacher_id', $teacherId)
            ->where('s.status', SessionStatus::Scheduled->value)
            ->where('s.starts_at', '>=', $from)
            ->orderBy('s.starts_at')
            ->select('s.id', 's.starts_at', 's.ends_at', 's.enrollment_id', 'sub.name_ar', 'sub.name_en', 'u.id as student_id', 'u.name as student', 'u.timezone as student_tz')
            ->get();

        $groups = [];
        foreach ($rows as $row) {
            $start = CarbonImmutable::parse($row->starts_at)->setTimezone($shifts->timezone);
            $minutes = (int) CarbonImmutable::parse($row->starts_at)->diffInMinutes(CarbonImmutable::parse($row->ends_at));
            $weekday = $start->dayOfWeekIso;
            $clock = $start->format('H:i');

            if ($shifts->covers($weekday, ShiftBlock::minutes($clock), $minutes)) {
                continue;
            }

            $key = implode('|', [$row->student_id ?? '-', $weekday, $clock, $minutes]);
            $groups[$key] ??= [
                'key' => $key,
                'student_id' => $row->student_id,
                'student' => $row->student ?? '—',
                'student_tz' => $row->student_tz ?? $fallbackTz,
                'enrollment_id' => $row->enrollment_id,
                'subject_ar' => $row->name_ar,
                'subject_en' => $row->name_en,
                'weekday' => $weekday,
                'start' => $clock,
                'duration_min' => $minutes,
                'count' => 0,
                'first' => $start,
                'last' => $start,
                'session_ids' => [],
            ];
            $groups[$key]['count']++;
            $groups[$key]['last'] = $start;
            $groups[$key]['session_ids'][] = $row->id;
        }

        return array_values($groups);
    }
}
