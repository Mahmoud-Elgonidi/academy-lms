<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Domain\Models\AcademyHoliday;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\SchedulingSettings;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Time\LocalTimeResolver;

/**
 * Turns weekly times into sessions up to the horizon (docs/architecture/01,
 * "توليد الحصص تلقائيًا"). Each date once: the occurrence key (slot + local
 * date) makes a second run a no-op. A date the teacher or the student is
 * already booked on is skipped and reported, never double-booked; a date on
 * an academy holiday is left out. Times are resolved on the teacher's clock,
 * so DST moves the student's side only.
 */
final class MaterializeSessions
{
    public function __construct(
        private readonly ScheduleSession $schedule,
        private readonly LocalTimeResolver $resolver,
    ) {}

    /**
     * @param  list<string>|null  $enrollmentIds  null: every enrollment whose times stay booked
     * @return array{created: list<Session>, skipped: list<array{slot_id: string, date: string}>}
     */
    public function handle(?array $enrollmentIds = null, ?CarbonImmutable $now = null, ?string $actorId = null): array
    {
        $now ??= CarbonImmutable::now();
        $horizon = SchedulingSettings::current()->horizon_weeks;

        $slots = RecurringSlot::query()
            ->select('recurring_slots.*', 'e.subject_id')
            ->join('enrollments as e', fn ($j) => $j->on('e.academy_id', '=', 'recurring_slots.academy_id')->on('e.id', '=', 'recurring_slots.enrollment_id'))
            ->where('recurring_slots.status', 'active')
            ->whereIn('e.status', ['pending_payment', 'active'])
            ->when($enrollmentIds !== null, fn ($q) => $q->whereIn('recurring_slots.enrollment_id', $enrollmentIds))
            ->get();

        $holidays = AcademyHoliday::query()->where('ends_at', '>', $now)->where('starts_at', '<', $now->addWeeks($horizon + 1))->get();

        $created = [];
        $skipped = [];
        foreach ($slots as $slot) {
            $today = $now->setTimezone($slot->anchor_tz)->startOfDay();
            $from = $slot->startsOn()->max($today);
            $to = $today->addWeeks($horizon);
            if ($slot->endsOn() !== null) {
                $to = $to->min($slot->endsOn());
            }

            $dates = $slot->weeklyTime()->datesBetween($from, $to);
            $done = Session::query()->where('slot_id', $slot->id)->whereIn('occurrence_key', array_map(fn ($d) => "{$slot->id}:{$d}", $dates))->pluck('occurrence_key')->all();

            foreach ($dates as $date) {
                $key = "{$slot->id}:{$date}";
                if (in_array($key, $done, true)) {
                    continue;
                }
                $startsAt = $this->resolver->resolve($date, $slot->weeklyTime()->clock(), $slot->anchor_tz)->utc;
                if ($startsAt->lessThanOrEqualTo($now) || $holidays->contains(fn (AcademyHoliday $h) => $h->contains($startsAt))) {
                    continue;
                }

                try {
                    $created[] = DB::transaction(fn () => $this->schedule->handle(new NewSession(
                        teacherId: $slot->teacher_id,
                        studentId: $slot->student_id,
                        subjectId: $slot->getAttribute('subject_id'),
                        startsAt: $startsAt,
                        durationMin: $slot->duration_min,
                        anchorTimezone: $slot->anchor_tz,
                        type: 'regular',
                        funding: 'unfunded',
                        enrollmentId: $slot->enrollment_id,
                        slotId: $slot->id,
                        occurrenceKey: $key,
                    ), $actorId));
                } catch (ValidationException) {
                    $skipped[] = ['slot_id' => $slot->id, 'date' => $date];
                }
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
