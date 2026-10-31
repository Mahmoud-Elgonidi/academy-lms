<?php

namespace Modules\Scheduling\Actions\Groups;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\CreateRecurringSlots;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\GroupMember;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;
use Modules\Scheduling\Domain\Slots\WeeklyTime;
use Modules\Scheduling\Queries\AvailabilityQuery;
use Modules\Scheduling\Support\MinorsGuard;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Time\Clock;

/**
 * A group's teacher changes for the whole group, by the academy, from a
 * date (docs/architecture/02, "الحصص الجماعية"): only to a teacher who
 * takes groups of at least its students (its seats come down to their most
 * when above it), cleared for every student under 18 in it (MinorsGuard),
 * and on shift and free for each of its booked lessons. The lessons keep
 * their times; the group's weekly times move to the new teacher's clock
 * (the old ones end the day before), and each member's course is the new
 * teacher's from that day.
 */
final class ReassignGroupTeacher
{
    public function __construct(
        private readonly PeopleApi $people,
        private readonly EnrollmentApi $enrollments,
        private readonly AvailabilityQuery $availability,
        private readonly CreateRecurringSlots $slots,
        private readonly MinorsGuard $minors,
        private readonly AuditTrail $audit,
    ) {}

    /** @return array{moved: int} booked lessons now the new teacher's */
    public function handle(string $groupId, string $teacherId, CarbonImmutable $from, ?string $actorId): array
    {
        $group = LessonGroup::query()->findOrFail($groupId);
        if ($group->status === LessonGroup::ENDED) {
            CreateGroup::refuse('group', 'ended');
        }
        if ($group->teacher_id === $teacherId) {
            throw ValidationException::withMessages(['teacher' => __('scheduling::policy.errors.same_teacher')]);
        }
        $members = GroupMember::query()->where('group_id', $group->id)
            ->where(fn ($q) => $q->whereNull('left_on')->orWhere('left_on', '>', $from->toDateString()))->get();
        $max = $this->people->groupCapacity($teacherId);
        if ($max === null) {
            throw ValidationException::withMessages(['teacher' => __('scheduling::group_outcomes.errors.teacher_no_groups')]);
        }
        if ($members->count() > $max) {
            throw ValidationException::withMessages(['teacher' => __('scheduling::group_outcomes.errors.teacher_too_small', ['max' => $max, 'count' => $members->count()])]);
        }
        $this->minors->assert($teacherId, $members->pluck('student_id')->all());

        $newTz = DB::table('users')->where('id', $teacherId)->value('timezone') ?? 'UTC';
        $fromUtc = CarbonImmutable::parse($from->toDateString(), $newTz)->utc()->max(CarbonImmutable::now());
        $lessons = Session::query()->where('group_id', $group->id)->where('status', SessionStatus::Scheduled->value)
            ->where('starts_at', '>=', $fromUtc)->orderBy('starts_at')->get();
        $oldSlots = RecurringSlot::query()->where('group_id', $group->id)->where('status', 'active')->get();

        if ($lessons->isNotEmpty()) {
            $free = $this->availability->forTeachers([$teacherId], $lessons->min('starts_at'), $lessons->max('ends_at'))[$teacherId]->free ?? null;
            $busy = $lessons->filter(fn (Session $s) => $free === null || ! $free->contains($s->starts_at->getTimestamp(), $s->ends_at->getTimestamp()));
            if ($busy->isNotEmpty()) {
                throw ValidationException::withMessages(['teacher' => __('scheduling::policy.errors.new_teacher_busy', [
                    'dates' => $busy->take(6)->map(fn (Session $s) => $s->starts_at->setTimezone($newTz)->locale(app()->getLocale())->translatedFormat(Clock::pattern('Y-m-d H:i')))->implode('، '),
                ])]);
            }
        }

        // Each weekly time at the same moment, read on the new teacher's clock from its first lesson ahead.
        $times = [];
        foreach ($oldSlots as $slot) {
            $first = $lessons->first(fn (Session $s) => $s->slot_id === $slot->id && ! $s->is_detached)?->starts_at;
            if ($first === null) {
                continue;
            }
            $local = $first->setTimezone($newTz);
            $times[$slot->id] = ['time' => WeeklyTime::at($local->dayOfWeekIso, $local->format('H:i'), $slot->duration_min), 'from' => $local->startOfDay()];
        }

        try {
            return DB::transaction(function () use ($group, $teacherId, $from, $fromUtc, $newTz, $lessons, $oldSlots, $times, $members, $max, $actorId) {
                foreach ($oldSlots as $slot) {
                    $until = $fromUtc->setTimezone($slot->anchor_tz)->toDateString();
                    DB::update(
                        "update recurring_slots set status = 'ended', updated_at = now(), effective = case when ?::date > lower(effective) then daterange(lower(effective), ?::date) else 'empty'::daterange end where id = ?",
                        [$until, $until, $slot->id],
                    );
                }
                $created = $times === [] ? [] : $this->slots->handle(null, $teacherId, null, $newTz, array_values($times), $actorId, groupId: $group->id);
                $map = $times === [] ? [] : array_combine(array_keys($times), array_map(fn (RecurringSlot $s) => $s->id, $created));

                foreach ($lessons as $lesson) {
                    $previous = $lesson->teacher_id;
                    $lesson->teacher_id = $teacherId;
                    $lesson->anchor_tz = $newTz;
                    if ($lesson->slot_id !== null && isset($map[$lesson->slot_id])) {
                        $date = $lesson->is_detached ? substr((string) $lesson->occurrence_key, -10) : $lesson->starts_at->setTimezone($newTz)->toDateString();
                        $lesson->slot_id = $map[$lesson->slot_id];
                        $lesson->occurrence_key = "{$lesson->slot_id}:{$date}";
                    }
                    $lesson->version++;
                    $lesson->save();
                    $lesson->participants()->where('role', ParticipantRole::Teacher->value)->update(['user_id' => $teacherId]);
                    SessionEvent::record($lesson, SessionEventKind::TeacherChanged, ['from' => $previous, 'to' => $teacherId], $actorId);
                }

                $previous = $group->teacher_id;
                $group->fill(['teacher_id' => $teacherId, 'anchor_tz' => $newTz, 'capacity' => min($group->capacity, $max)])->save();
                foreach ($members as $member) {
                    $this->enrollments->reassignTeacher($member->enrollment_id, $teacherId, $from, $actorId);
                    $this->audit->record('enrollment.teacher_changed', 'enrollment', $member->enrollment_id, [
                        'from' => $previous, 'to' => $teacherId, 'starting' => $from->toDateString(), 'lessons_moved' => $lessons->count(), 'group' => true,
                    ]);
                }

                return ['moved' => $lessons->count()];
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'session_participants_no_double_booking') || str_contains($e->getMessage(), 'recurring_slots_no_overlap')) {
                throw ValidationException::withMessages(['teacher' => __('scheduling::policy.errors.someone_busy')]);
            }

            throw $e;
        }
    }
}
