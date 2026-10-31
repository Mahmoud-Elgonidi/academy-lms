<?php

namespace Modules\Scheduling\Actions\Groups;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Actions\FundSessions;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Contracts\Events\GroupStarted;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Support\CourseDays;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * A forming group has the students it waited for (its minimum, or its
 * first): it runs from today on the academy's calendar, its lessons are
 * made up to the horizon with every member seated, and each member's credit
 * funds their own seats. Every member hears it (GroupStarted). Starting a
 * group that already runs does nothing.
 */
final class StartGroup
{
    public function __construct(
        private readonly MaterializeSessions $materialize,
        private readonly FundSessions $fund,
        private readonly CourseDays $days,
    ) {}

    /** @return int lessons made */
    public function handle(LessonGroup $group, ?string $actorId, ?CarbonImmutable $now = null): int
    {
        return DB::transaction(function () use ($group, $actorId, $now) {
            $group = LessonGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            if ($group->status !== LessonGroup::FORMING) {
                return 0;
            }
            $today = $this->days->today($now);
            $group->status = LessonGroup::RUNNING;
            $group->started_on = $today;
            $group->save();

            $made = count($this->materialize->groups([$group->id], $now, $actorId)['created']);
            $members = $group->membersOn($today->toDateString())->get();
            foreach ($members as $member) {
                $this->fund->handle($member->enrollment_id, $now);
            }

            app(AuditTrail::class)->record('lesson_group.started', 'lesson_group', $group->id, ['members' => $members->count(), 'lessons' => $made]);
            DB::afterCommit(fn () => event(new GroupStarted($group->academy_id, $group->id, $group->teacher_id, $members->pluck('student_id')->values()->all(), $today->toDateString(), $actorId)));

            return $made;
        });
    }
}
