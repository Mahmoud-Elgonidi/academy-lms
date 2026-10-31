<?php

namespace Modules\Scheduling\Actions\Groups;

use Illuminate\Support\Facades\DB;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Domain\Groups\GroupRules;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Support\CourseDays;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Changes a group's seats (and the students it waits for while forming):
 * at most the teacher's most, never below the seats already taken, and the
 * minimum within the seats. Under the group's lock, so it cannot cross a join.
 */
final class SaveGroupCapacity
{
    public function __construct(
        private readonly PeopleApi $people,
        private readonly CourseDays $days,
    ) {}

    public function handle(string $groupId, int $capacity, ?string $actorId, ?int $minToStart = null): LessonGroup
    {
        return DB::transaction(function () use ($groupId, $capacity, $minToStart) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['group:'.$groupId]);
            $group = LessonGroup::query()->whereKey($groupId)->lockForUpdate()->firstOrFail();
            if ($group->status === LessonGroup::ENDED) {
                CreateGroup::refuse('group', 'ended');
            }
            $taken = $group->seatsTaken($this->days->today()->toDateString());
            $max = $this->people->groupCapacity($group->teacher_id);
            CreateGroup::refuse('capacity', GroupRules::capacity($capacity, $max, $taken), ['max' => $max, 'count' => $taken]);
            // A minimum typed is checked as it is; the one it had follows fewer seats down.
            $min = $minToStart ?? ($group->min_to_start === null ? null : min($group->min_to_start, $capacity));
            CreateGroup::refuse('min_to_start', GroupRules::minToStart($min, $capacity), ['capacity' => $capacity]);

            $from = $group->capacity;
            $group->capacity = $capacity;
            $group->min_to_start = $min;
            $group->save();
            app(AuditTrail::class)->record('lesson_group.capacity', 'lesson_group', $group->id, array_filter(['from' => $from, 'to' => $capacity, 'min_to_start' => $min], fn ($v) => $v !== null));

            return $group;
        });
    }
}
