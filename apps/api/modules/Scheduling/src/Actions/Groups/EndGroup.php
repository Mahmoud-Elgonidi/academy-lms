<?php

namespace Modules\Scheduling\Actions\Groups;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\Courses\EndEnrollment;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Support\CourseDays;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Ends a group from today, by the office: each member's course ends the
 * proper way (EndEnrollment, reason group_ended: their seats come out of
 * the group's lessons with what was set aside given back, and the course's
 * billing closes as for any course that ends), then the group's booked
 * lessons left are cancelled (CancelSession, rule 22: nothing lost), its
 * weekly times end today, and it stays ended. Audited with its counts.
 */
final class EndGroup
{
    public const REASON = 'group_ended';

    public function __construct(
        private readonly EndEnrollment $endEnrollment,
        private readonly CancelSession $cancel,
        private readonly CourseDays $days,
    ) {}

    /** @return array{courses: int, cancelled: int} */
    public function handle(string $groupId, string $reason, ?string $actorId): array
    {
        return DB::transaction(function () use ($groupId, $reason, $actorId) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['group:'.$groupId]);
            $group = LessonGroup::query()->whereKey($groupId)->lockForUpdate()->firstOrFail();
            if ($group->status === LessonGroup::ENDED) {
                CreateGroup::refuse('group', 'ended');
            }
            $today = $this->days->today();

            $courses = 0;
            foreach ($group->members()->where(fn ($q) => $q->whereNull('left_on')->orWhere('left_on', '>', $today->toDateString()))->get() as $member) {
                try {
                    $this->endEnrollment->handle($member->enrollment_id, $today->toDateString(), trim($reason) !== '' ? trim($reason) : self::REASON, $actorId);
                    $courses++;
                } catch (ValidationException) {
                    // Ended already (or ending sooner): their seat goes with the group below.
                }
                $member->refresh();
                if ($member->left_on === null || $member->left_on->greaterThan($today)) {
                    $member->left_on = $today->max($member->joined_on);
                    $member->save();
                }
            }

            $cancelled = 0;
            $lessons = Session::query()->with('participants')->where('group_id', $group->id)->where('status', SessionStatus::Scheduled->value)
                ->where('starts_at', '>', CarbonImmutable::now())->orderBy('starts_at')->get();
            foreach ($lessons as $lesson) {
                try {
                    $this->cancel->handle($lesson, 'system', self::REASON, null, $actorId);
                    $cancelled++;
                } catch (ValidationException) {
                    continue;
                }
            }

            $until = $today->toDateString();
            foreach ($group->slots()->where('status', 'active')->get() as $slot) {
                DB::update(
                    'update recurring_slots set status = ?, updated_at = now(), effective = case when ?::date > lower(effective) '
                    ."then daterange(lower(effective), least(coalesce(upper(effective), ?::date), ?::date)) else 'empty'::daterange end where id = ?",
                    ['ended', $until, $until, $until, $slot->id],
                );
            }
            $group->status = LessonGroup::ENDED;
            $group->ended_on = $today;
            $group->save();

            app(AuditTrail::class)->record('lesson_group.ended', 'lesson_group', $group->id, ['courses' => $courses, 'lessons' => $cancelled]);

            return ['courses' => $courses, 'cancelled' => $cancelled];
        });
    }
}
