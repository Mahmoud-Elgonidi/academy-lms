<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Scheduling\Actions\Groups\GroupSeats;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Domain\Models\AcademyHoliday;
use Modules\Scheduling\Domain\Models\GroupMember;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\SchedulingSettings;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Support\CourseDays;
use Modules\SharedKernel\Time\LocalTimeResolver;

/**
 * Turns weekly times into sessions up to the horizon (docs/architecture/01,
 * "توليد الحصص تلقائيًا"). Each date once: the occurrence key (slot + local
 * date) makes a second run a no-op. A date the teacher or the student is
 * already booked on is skipped and reported, never double-booked; a date on
 * an academy holiday is left out, and so is one of a course's paused days
 * or after its end (CourseDays). A paused or ended course makes none at all.
 * Times are resolved on the teacher's clock,
 * so DST moves the student's side only. Two runs at once (the nightly one
 * and a teacher change) make each date once: the unique occurrence key
 * holds it, and the run that comes second leaves the date alone.
 *
 * A running group's weekly times make one lesson a date with its teacher,
 * and every member in the group that day whose course has lessons then is
 * seated in it (their own seat, unpaid until their credit funds it); a
 * member booked elsewhere then is left out of that lesson, not the others.
 * A group still forming, or ended, makes none.
 */
final class MaterializeSessions
{
    public function __construct(
        private readonly ScheduleSession $schedule,
        private readonly LocalTimeResolver $resolver,
        private readonly CourseDays $days,
        private readonly GroupSeats $seats,
        private readonly EnrollmentApi $enrollments,
    ) {}

    /**
     * @param  list<string>|null  $enrollmentIds  null: every enrollment whose times stay booked, and every running group
     * @return array{created: list<Session>, skipped: list<array{slot_id: string, date: string}>}
     */
    public function handle(?array $enrollmentIds = null, ?CarbonImmutable $now = null, ?string $actorId = null): array
    {
        $courses = $this->courses($enrollmentIds, $now, $actorId);
        if ($enrollmentIds !== null) {
            return $courses;
        }
        $groups = $this->groups(null, $now, $actorId);

        return ['created' => [...$courses['created'], ...$groups['created']], 'skipped' => [...$courses['skipped'], ...$groups['skipped']]];
    }

    /**
     * A running group's lessons up to the horizon, each with the members in the group that day.
     *
     * @param  list<string>|null  $groupIds  null: every running group
     * @return array{created: list<Session>, skipped: list<array{slot_id: string, date: string}>}
     */
    public function groups(?array $groupIds = null, ?CarbonImmutable $now = null, ?string $actorId = null): array
    {
        $now ??= CarbonImmutable::now();
        $horizon = SchedulingSettings::current()->horizon_weeks;
        $slots = RecurringSlot::query()
            ->select('recurring_slots.*', 'g.subject_id')
            ->join('lesson_groups as g', fn ($j) => $j->on('g.academy_id', '=', 'recurring_slots.academy_id')->on('g.id', '=', 'recurring_slots.group_id'))
            ->where('recurring_slots.status', 'active')->where('g.status', LessonGroup::RUNNING)
            ->when($groupIds !== null, fn ($q) => $q->whereIn('recurring_slots.group_id', $groupIds))
            ->get();
        if ($slots->isEmpty()) {
            return ['created' => [], 'skipped' => []];
        }
        $holidays = AcademyHoliday::query()->where('ends_at', '>', $now)->where('starts_at', '<', $now->addWeeks($horizon + 1))->get();
        $members = GroupMember::query()->whereIn('group_id', $slots->pluck('group_id')->unique()->values())->get()->groupBy('group_id');
        $courses = [];

        $created = [];
        $skipped = [];
        foreach ($slots as $slot) {
            $today = $now->setTimezone($slot->anchor_tz)->startOfDay();
            $to = $today->addWeeks($horizon);
            if ($slot->endsOn() !== null) {
                $to = $to->min($slot->endsOn());
            }
            $dates = $slot->weeklyTime()->datesBetween($slot->startsOn()->max($today), $to);
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
                $day = $this->days->today($startsAt)->toDateString();

                try {
                    $created[] = DB::transaction(function () use ($slot, $startsAt, $key, $day, $members, &$courses, $actorId) {
                        $lesson = $this->schedule->handle(new NewSession(
                            teacherId: $slot->teacher_id,
                            studentId: null,
                            subjectId: $slot->getAttribute('subject_id'),
                            startsAt: $startsAt,
                            durationMin: $slot->duration_min,
                            anchorTimezone: $slot->anchor_tz,
                            type: 'regular',
                            funding: 'unfunded',
                            slotId: $slot->id,
                            occurrenceKey: $key,
                            groupId: $slot->group_id,
                        ), $actorId);
                        foreach ($members->get($slot->group_id, collect()) as $member) {
                            /** @var GroupMember $member */
                            if (! $member->liveOn($day)) {
                                continue;
                            }
                            $course = $courses[$member->enrollment_id] ??= $this->enrollments->find($member->enrollment_id);
                            if ($course === null || ! $course->status->makesLessons() || $this->days->barredFor($course, $startsAt) !== null) {
                                continue;
                            }
                            $this->seats->seat($lesson, $member->student_id, $member->enrollment_id, $actorId);
                        }

                        return $lesson;
                    });
                } catch (ValidationException) {
                    $skipped[] = ['slot_id' => $slot->id, 'date' => $date];
                } catch (UniqueConstraintViolationException $e) {
                    if (! str_contains($e->getMessage(), 'occurrence_key')) {
                        throw $e;
                    }
                }
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @param  list<string>|null  $enrollmentIds
     * @return array{created: list<Session>, skipped: list<array{slot_id: string, date: string}>}
     */
    private function courses(?array $enrollmentIds, ?CarbonImmutable $now, ?string $actorId): array
    {
        $now ??= CarbonImmutable::now();
        $horizon = SchedulingSettings::current()->horizon_weeks;

        $slots = RecurringSlot::query()
            ->select('recurring_slots.*', 'e.subject_id', 'e.paused_from', 'e.paused_until', 'e.ends_on')
            ->join('enrollments as e', fn ($j) => $j->on('e.academy_id', '=', 'recurring_slots.academy_id')->on('e.id', '=', 'recurring_slots.enrollment_id'))
            ->where('recurring_slots.status', 'active')
            ->whereIn('e.status', EnrollmentStatus::making())
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
                if ($startsAt->lessThanOrEqualTo($now) || $holidays->contains(fn (AcademyHoliday $h) => $h->contains($startsAt))
                    || $this->days->barred($slot->getAttribute('paused_from'), $slot->getAttribute('paused_until'), $slot->getAttribute('ends_on'), $startsAt) !== null) {
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
                } catch (UniqueConstraintViolationException $e) {
                    // Another run (the nightly one, a teacher change) made this date's lesson a moment ago.
                    if (! str_contains($e->getMessage(), 'occurrence_key')) {
                        throw $e;
                    }
                }
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
