<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Actions\Dst\PlanDstChanges;
use Modules\Scheduling\Actions\TimeOff\RequestTimeOff;
use Modules\Scheduling\Contracts\Events\TeacherLessonsShifted;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Models\RecurringSlot;

/**
 * Bulk rescheduling (docs/architecture/01, "إعادة جدولة جماعية"): every
 * weekly time of one teacher moves by the same minutes on their clock from
 * one day to another (both included, on their clock), and comes back after
 * it; Ramadan hours, say. All of it or nothing: ShiftSlotTimes checks every
 * lesson first. Each family hears its new time, the teacher once.
 */
final class ShiftTeacherLessons
{
    public const MINUTES = [-120, -90, -60, -30, 30, 60, 90, 120];

    public function __construct(
        private readonly ShiftSlotTimes $shift,
        private readonly PlanDstChanges $planner,
        private readonly RequestTimeOff $days,
    ) {}

    /**
     * What would move, said before it does.
     *
     * @return array{lessons: int, students: int, problems: list<array{start: CarbonImmutable, why: string}>, timezone: string}
     */
    public function preview(string $teacherId, string $firstDay, string $lastDay, int $minutes): array
    {
        [$slots, $from, $until, $tz] = $this->period($teacherId, $firstDay, $lastDay);
        ['moves' => $moves, 'problems' => $problems] = $this->shift->plan($slots, $from, $until, $minutes);

        return [
            'lessons' => count($moves),
            'students' => collect($moves)->map(fn (array $m) => $m['session']->participant(ParticipantRole::Student)?->user_id)->filter()->unique()->count(),
            'problems' => array_map(fn (array $p) => ['start' => $p['start'], 'why' => $p['why']], $problems),
            'timezone' => $tz,
        ];
    }

    /** @return int booked lessons moved */
    public function handle(string $teacherId, string $firstDay, string $lastDay, int $minutes, string $actorId): int
    {
        if (! in_array($minutes, self::MINUTES, true)) {
            throw ValidationException::withMessages(['minutes' => __('scheduling::dst.errors.minutes')]);
        }
        [$slots, $from, $until] = $this->period($teacherId, $firstDay, $lastDay);

        return DB::transaction(function () use ($teacherId, $slots, $from, $until, $minutes, $actorId, $firstDay, $lastDay) {
            $moved = $this->shift->handle($slots, $from, $until, $minutes, $actorId, 'academy');
            // A clock change inside the period now falls on other times.
            $this->planner->handle(enrollmentIds: array_values(array_unique(array_map(fn (RecurringSlot $s) => $s->enrollment_id, $slots))));
            if ($slots !== []) {
                DB::afterCommit(fn () => event(new TeacherLessonsShifted($slots[0]->academy_id, $teacherId, $firstDay, $lastDay, $minutes, $moved, $actorId)));
            }

            return $moved;
        });
    }

    /** @return array{0: list<RecurringSlot>, 1: CarbonImmutable, 2: CarbonImmutable, 3: string} the teacher's live weekly times in the period, its bounds, and their clock */
    private function period(string $teacherId, string $firstDay, string $lastDay): array
    {
        $tz = $this->days->timezone($teacherId);
        if ($lastDay < $firstDay) {
            throw ValidationException::withMessages(['to' => __('scheduling::dst.errors.dates')]);
        }
        if ($firstDay < CarbonImmutable::now()->setTimezone($tz)->toDateString()) {
            throw ValidationException::withMessages(['from' => __('scheduling::dst.errors.past')]);
        }
        [$from, $until] = $this->days->bounds($tz, $firstDay, $lastDay);
        $slots = RecurringSlot::query()->where('teacher_id', $teacherId)->where('status', 'active')
            ->whereRaw('effective && daterange(?::date, ?::date)', [$firstDay, CarbonImmutable::parse($lastDay)->addDay()->toDateString()])
            ->orderBy('minute_of_week')->get()->all();

        return [$slots, $from, $until, $tz];
    }
}
