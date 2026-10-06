<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Contracts\Events\SlotTimesShifted;
use Modules\Scheduling\Domain\Availability\IntervalSet;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;
use Modules\Scheduling\Domain\Models\SessionParticipant;
use Modules\Scheduling\Domain\Slots\WeeklyTime;
use Modules\Scheduling\Queries\AvailabilityQuery;

/**
 * Moves one teacher's weekly times by some minutes for a period
 * (docs/architecture/01, "إعادة جدولة جماعية"; 02, the DST planner's "keep
 * the student's time"). Weekly times stay on the teacher's clock, so the
 * exclusion on recurring_slots keeps comparing like with like: each slot ends
 * before the period, a moved copy covers the period, and the original time
 * comes back after it. Booked lessons in the period move with their credit;
 * every lesson of the period and after it follows its new slot, so none is
 * generated twice or brought back once cancelled. The teacher must be on
 * shift and free, and each student free, at every new time (the lessons being
 * moved do not count against themselves); the database refuses the rest.
 */
final class ShiftSlotTimes
{
    public function __construct(
        private readonly AvailabilityQuery $availability,
        private readonly CreateRecurringSlots $slots,
        private readonly MaterializeSessions $materialize,
    ) {}

    /**
     * The booked lessons that would move, and those that cannot.
     *
     * @param  iterable<RecurringSlot>  $slots  weekly times of one teacher
     * @return array{moves: list<array{session: Session, start: CarbonImmutable, end: CarbonImmutable}>, problems: list<array{session: Session, start: CarbonImmutable, why: string}>}
     */
    public function plan(iterable $slots, CarbonImmutable $from, ?CarbonImmutable $until, int $minutes): array
    {
        $ids = collect($slots)->pluck('id')->all();
        $lessons = Session::query()->with('participants')->whereIn('slot_id', $ids)->where('status', SessionStatus::Scheduled->value)->where('is_detached', false)
            ->where('starts_at', '>=', $from->max(CarbonImmutable::now())->utc())
            ->when($until, fn ($q) => $q->where('starts_at', '<', $until->utc()))
            ->orderBy('starts_at')->get();
        $moves = $lessons->map(fn (Session $s) => ['session' => $s, 'start' => $s->starts_at->addMinutes($minutes), 'end' => $s->ends_at->addMinutes($minutes)])->all();

        return ['moves' => $moves, 'problems' => $this->problems(collect($slots)->first()?->teacher_id, $moves)];
    }

    /**
     * @param  list<RecurringSlot>  $slots  live weekly times of one teacher
     * @return int how many booked lessons moved
     */
    public function handle(array $slots, CarbonImmutable $from, ?CarbonImmutable $until, int $minutes, ?string $actorId, string $reason = 'academy'): int
    {
        if ($minutes === 0 || abs($minutes) >= WeeklyTime::WEEK_MIN) {
            throw ValidationException::withMessages(['minutes' => __('scheduling::dst.errors.minutes')]);
        }
        if ($slots === []) {
            return 0;
        }
        ['moves' => $moves, 'problems' => $problems] = $this->plan($slots, $from, $until, $minutes);
        if ($problems !== []) {
            $tz = $slots[0]->anchor_tz;
            throw ValidationException::withMessages(['slots' => __('scheduling::dst.errors.busy', [
                'dates' => collect($problems)->take(6)->map(fn (array $p) => $p['start']->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M H:i'))->implode('، '),
            ])]);
        }

        $counts = collect($moves)->countBy(fn (array $m) => $m['session']->slot_id);

        try {
            return DB::transaction(function () use ($slots, $from, $until, $minutes, $actorId, $reason, $moves, $counts) {
                // Every old weekly time ends first, so the moved ones never meet them.
                $periods = [];
                foreach ($slots as $slot) {
                    $periods[$slot->id] = $this->period($slot, $from, $until, $minutes);
                    $start = $periods[$slot->id]['start'];
                    DB::update(
                        "update recurring_slots set status = 'ended', updated_at = now(), effective = case when ?::date > lower(effective) then daterange(lower(effective), ?::date) else 'empty'::daterange end where id = ?",
                        [$start, $start, $slot->id],
                    );
                }

                $shifted = [];
                foreach ($slots as $slot) {
                    ['start' => $start, 'end' => $end, 'origEnd' => $origEnd, 'moved' => $moved, 'shiftDate' => $shiftDate] = $periods[$slot->id];
                    $periodEnd = $end !== null && ($origEnd === null || $end < $origEnd) ? $end : $origEnd;
                    $times = [['time' => $moved, 'from' => CarbonImmutable::parse($shiftDate($start)), 'until' => $periodEnd ? CarbonImmutable::parse($shiftDate($periodEnd)) : null]];
                    if ($end !== null && ($origEnd === null || $end < $origEnd)) {
                        $times[] = ['time' => $slot->weeklyTime(), 'from' => CarbonImmutable::parse($end), 'until' => $origEnd ? CarbonImmutable::parse($origEnd) : null];
                    }
                    $created = $this->slots->handle($slot->enrollment_id, $slot->teacher_id, $slot->student_id, $slot->anchor_tz, $times, $actorId);
                    $shifted[$slot->id] = $created[0];

                    // Every lesson of the old slot from the period on follows its new slot (by its occurrence date),
                    // but one already behind us that did not move stays where it was.
                    $moving = array_map(fn (array $m) => $m['session']->id, $moves);
                    foreach (Session::query()->where('slot_id', $slot->id)->whereNotNull('occurrence_key')->get() as $s) {
                        $date = substr((string) $s->occurrence_key, -10);
                        if ($date < $start || ($s->starts_at->isPast() && ! in_array($s->id, $moving, true))) {
                            continue;
                        }
                        $target = isset($created[1]) && $date >= $end ? $created[1] : $created[0];
                        $s->slot_id = $target->id;
                        $s->occurrence_key = $target->id.':'.($target === $created[0] ? $shiftDate($date) : $date);
                        $s->save();
                    }
                }

                // Later lessons first when moving later (earlier ones first when moving earlier), so none lands on one not yet moved.
                $ordered = collect($moves)->sortBy(fn (array $m) => $m['session']->starts_at->getTimestamp() * ($minutes > 0 ? -1 : 1))->values();
                foreach ($ordered as ['session' => $session, 'start' => $start, 'end' => $end]) {
                    /** @var Session $session */
                    $old = $session->starts_at;
                    $session->refresh();
                    $session->starts_at = $start;
                    $session->ends_at = $end;
                    $session->version++;
                    $session->save();
                    $period = SessionParticipant::blockPeriod($start, $end);
                    foreach ([ParticipantRole::Teacher, ParticipantRole::Student] as $role) {
                        $session->participants()->where('role', $role->value)->where('is_active', true)->update(['block_period' => $period]);
                    }
                    SessionEvent::record($session, SessionEventKind::Rescheduled, ['from' => $old->toIso8601String(), 'to' => $start->toIso8601String(), 'by' => 'academy', 'reason' => $reason, 'minutes' => $minutes], $actorId);
                }

                $this->materialize->handle(array_values(array_unique(array_map(fn (RecurringSlot $s) => $s->enrollment_id, $slots))), actorId: $actorId);

                foreach ($slots as $slot) {
                    $count = $counts[$slot->id] ?? 0;
                    DB::afterCommit(fn () => event(new SlotTimesShifted(
                        $slot->academy_id, $shifted[$slot->id]->id, $slot->enrollment_id, $slot->student_id, $slot->teacher_id,
                        $from->utc()->toIso8601ZuluString(), $until?->utc()->toIso8601ZuluString(), $minutes, $count, $reason, $actorId,
                    )));
                }

                return count($moves);
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'session_participants_no_double_booking') || str_contains($e->getMessage(), 'recurring_slots_no_overlap')) {
                throw ValidationException::withMessages(['slots' => __('scheduling::policy.errors.someone_busy')]);
            }

            throw $e;
        }
    }

    /**
     * The period in local dates on the slot's clock, and the weekly time it takes there.
     *
     * @return array{start: string, end: ?string, origEnd: ?string, moved: WeeklyTime, shiftDate: callable(string): string}
     */
    private function period(RecurringSlot $slot, CarbonImmutable $from, ?CarbonImmutable $until, int $minutes): array
    {
        $tz = $slot->anchor_tz;
        $week = $slot->weeklyTime();
        $newMinute = (($week->weekday - 1) * 1440 + $week->startMin + $minutes) % WeeklyTime::WEEK_MIN;
        $newMinute = $newMinute < 0 ? $newMinute + WeeklyTime::WEEK_MIN : $newMinute;
        $dayShift = (int) floor(($week->startMin + $minutes) / 1440);

        return [
            'start' => max($from->setTimezone($tz)->toDateString(), $slot->startsOn()->toDateString()),
            'end' => $until?->setTimezone($tz)->toDateString(),
            'origEnd' => $slot->endsOn()?->addDay()->toDateString(),          // exclusive, as in the range
            'moved' => new WeeklyTime(intdiv($newMinute, 1440) + 1, $newMinute % 1440, $week->durationMin),
            'shiftDate' => fn (string $date) => CarbonImmutable::parse($date)->addDays($dayShift)->toDateString(),
        ];
    }

    /**
     * @param  list<array{session: Session, start: CarbonImmutable, end: CarbonImmutable}>  $moves
     * @return list<array{session: Session, start: CarbonImmutable, why: string}>
     */
    private function problems(?string $teacherId, array $moves): array
    {
        if ($moves === [] || $teacherId === null) {
            return [];
        }
        $first = collect($moves)->min('start');
        $last = collect($moves)->max('end');
        $teacher = $this->availability->forTeachers([$teacherId], $first->subHour(), $last->addHour())[$teacherId] ?? null;
        // The lessons being moved do not stand in their own way.
        $own = array_map(fn (array $m) => [$m['session']->starts_at->getTimestamp(), $m['session']->ends_at->getTimestamp()], $moves);
        $free = new IntervalSet(array_merge($teacher?->free->intervals ?? [], $own));
        $students = collect($moves)->map(fn (array $m) => $m['session']->participant(ParticipantRole::Student)?->user_id)->filter()->unique()->values()->all();
        $blocks = DB::table('session_participants')->whereIn('user_id', $students)->where('is_active', true)
            ->whereNotIn('session_id', array_map(fn (array $m) => $m['session']->id, $moves))
            ->whereRaw('block_period && tstzrange(?, ?)', [$first->utc(), $last->utc()])
            ->selectRaw('user_id, extract(epoch from lower(block_period))::bigint as s, extract(epoch from upper(block_period))::bigint as e')
            ->get()->groupBy('user_id');

        $problems = [];
        foreach ($moves as ['session' => $session, 'start' => $start, 'end' => $end]) {
            [$s, $e] = [$start->getTimestamp(), $end->getTimestamp()];
            $student = $session->participant(ParticipantRole::Student)?->user_id;
            $why = match (true) {
                $teacher === null || ! $teacher->shifts->contains($s, $e) || ! $free->contains($s, $e) => 'teacher',
                ($blocks[$student] ?? collect())->contains(fn ($b) => (int) $b->s < $e && (int) $b->e > $s) => 'student',
                default => null,
            };
            if ($why !== null) {
                $problems[] = ['session' => $session, 'start' => $start, 'why' => $why];
            }
        }

        return $problems;
    }
}
