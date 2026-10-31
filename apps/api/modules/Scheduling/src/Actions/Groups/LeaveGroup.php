<?php

namespace Modules\Scheduling\Actions\Groups;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Domain\Models\GroupMember;
use Modules\Scheduling\Support\CourseDays;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * A member's course leaves its group from a day (its end: EndEnrollment
 * calls this for a course in a group): the seat is theirs until that day,
 * then free for someone else, and they are taken out of their own group
 * lessons from then, with what was set aside for them given back. The
 * group's lessons go on for everyone else; one student's change never
 * touches the others'. A course with no membership is left alone.
 */
final class LeaveGroup
{
    public function __construct(
        private readonly GroupSeats $seats,
        private readonly CourseDays $days,
    ) {}

    /** @return ?GroupMember the membership that ended, or null when the course is in no group */
    public function handle(string $enrollmentId, CarbonImmutable $day, ?string $actorId, string $reason = 'enrollment_ended'): ?GroupMember
    {
        return DB::transaction(function () use ($enrollmentId, $day, $actorId, $reason) {
            $member = GroupMember::query()->where('enrollment_id', $enrollmentId)->whereNull('left_on')->lockForUpdate()->first();
            if ($member === null) {
                return null;
            }
            $leftOn = $day->max($member->joined_on);
            $member->left_on = $leftOn;
            $member->save();
            $removed = $this->seats->unseatFrom($enrollmentId, $this->days->startOf($leftOn), null, $actorId, $reason);

            app(AuditTrail::class)->record('lesson_group.left', 'lesson_group', $member->group_id, [
                'enrollment' => $enrollmentId, 'on' => $leftOn->toDateString(), 'lessons' => $removed,
            ]);

            return $member;
        });
    }

    /** The course is in a group (its lessons are the group's, not its own). */
    public static function memberOf(string $enrollmentId): ?GroupMember
    {
        return GroupMember::query()->where('enrollment_id', $enrollmentId)->whereNull('left_on')->first();
    }
}
