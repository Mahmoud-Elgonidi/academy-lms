<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Slots\WeeklyTime;

/**
 * Books an enrollment's weekly times with its teacher, or a group's (then no
 * enrollment and no student: $groupId). The database refuses a weekly time
 * that overlaps another live one of the same teacher.
 */
final class CreateRecurringSlots
{
    /**
     * @param  array<int, array{time: WeeklyTime, from: CarbonImmutable, until?: ?CarbonImmutable}>  $times  on the teacher's clock, each from its first local date (and up to a local date, excluded)
     * @return list<RecurringSlot>
     */
    public function handle(?string $enrollmentId, string $teacherId, ?string $studentId, string $anchorTz, array $times, ?string $actorId, ?string $groupId = null): array
    {
        try {
            return DB::transaction(fn () => array_map(fn (array $t) => RecurringSlot::query()->create([
                'enrollment_id' => $enrollmentId,
                'group_id' => $groupId,
                'teacher_id' => $teacherId,
                'student_id' => $studentId,
                'weekday' => $t['time']->weekday,
                'local_start' => $t['time']->clock(),
                'duration_min' => $t['time']->durationMin,
                'anchor_tz' => $anchorTz,
                'minute_of_week' => $t['time']->minuteOfWeek(),
                'effective' => '['.$t['from']->toDateString().','.(isset($t['until']) ? $t['until']->toDateString() : '').')',
                'created_by' => $actorId,
            ]), array_values($times)));
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'recurring_slots_no_overlap')) {
                throw ValidationException::withMessages(['slots' => __('scheduling::find.errors.slot_taken')]);
            }

            throw $e;
        }
    }
}
