<?php

namespace Modules\Scheduling\Queries\Groups;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\SchedulingSettings;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Support\CourseDays;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\SharedKernel\Time\LocalTimeResolver;

/**
 * The groups a student could join (Find a teacher's group mode; docs/
 * architecture/02, "الحصص الجماعية"): forming or running, with a free seat,
 * of the subject (and the student's level and ages when the group has
 * them, unless the academy allows mixed groups), with their weekly times on
 * the student's clock, their seats, the lessons held so far and the next
 * one. Teachers by name only, never a contact.
 */
final class OpenGroups
{
    public function __construct(
        private readonly CourseDays $days,
        private readonly LocalTimeResolver $resolver,
        private readonly LocalTimePresenter $times,
    ) {}

    /**
     * @param  list<string>|null  $teacherIds
     * @return list<array{id: string, name: ?string, teacher_id: string, teacher_name: string, subject_id: string, level_id: ?string, age_group: ?string,
     *                    status: string, capacity: int, taken: int, free: int, minutes: int, per_week: int,
     *                    times: list<array{weekday: int, start: string}>, held: int, next: ?array}>
     */
    public function handle(string $subjectId, ?string $levelId = null, ?string $ageGroup = null, ?string $studentTimezone = null, ?array $teacherIds = null, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $today = $this->days->today($now)->toDateString();
        $mixed = SchedulingSettings::current()->allow_mixed_ages;
        $groups = LessonGroup::query()->whereIn('status', [LessonGroup::FORMING, LessonGroup::RUNNING])->where('subject_id', $subjectId)
            ->when($levelId !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('level_id')->orWhere('level_id', $levelId)))
            ->when($ageGroup !== null && ! $mixed, fn ($q) => $q->where(fn ($w) => $w->whereNull('age_group')->orWhere('age_group', $ageGroup)))
            ->when($teacherIds !== null, fn ($q) => $q->whereIn('teacher_id', $teacherIds))
            ->withCount(['members as taken' => fn ($q) => $q->where(fn ($w) => $w->whereNull('left_on')->orWhere('left_on', '>', $today))])
            ->orderBy('created_at')->get()
            ->filter(fn (LessonGroup $g) => (int) $g->getAttribute('taken') < $g->capacity)->values();
        if ($groups->isEmpty()) {
            return [];
        }
        $ids = $groups->modelKeys();
        $names = DB::table('users')->whereIn('id', $groups->pluck('teacher_id')->unique()->values())->pluck('name', 'id');
        $slots = RecurringSlot::query()->whereIn('group_id', $ids)->where('status', 'active')->get()->groupBy('group_id');
        $held = Session::query()->whereIn('group_id', $ids)->whereIn('status', [SessionStatus::Completed->value, SessionStatus::StudentAbsent->value])
            ->groupBy('group_id')->selectRaw('group_id, count(*) as n')->pluck('n', 'group_id');
        $next = Session::query()->whereIn('group_id', $ids)->where('status', SessionStatus::Scheduled->value)->where('starts_at', '>', $now)
            ->groupBy('group_id')->selectRaw('group_id, min(starts_at) as at')->pluck('at', 'group_id');
        $zone = $studentTimezone ?? $this->days->timezone();

        return $groups->map(fn (LessonGroup $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'teacher_id' => $g->teacher_id,
            'teacher_name' => (string) ($names[$g->teacher_id] ?? ''),
            'subject_id' => $g->subject_id,
            'level_id' => $g->level_id,
            'age_group' => $g->age_group,
            'status' => $g->status,
            'capacity' => $g->capacity,
            'taken' => (int) $g->getAttribute('taken'),
            'free' => $g->capacity - (int) $g->getAttribute('taken'),
            'minutes' => $g->session_minutes,
            'per_week' => $g->sessions_per_week,
            'times' => $slots->get($g->id, collect())->map(fn (RecurringSlot $s) => $this->onClock($s, $zone, $now))->sortBy(fn ($t) => $t['weekday'] * 1440 + (int) substr($t['start'], 0, 2) * 60 + (int) substr($t['start'], 3, 2))->values()->all(),
            'held' => (int) ($held[$g->id] ?? 0),
            'next' => isset($next[$g->id]) ? $this->times->present(CarbonImmutable::parse($next[$g->id]), $zone) : null,
        ])->all();
    }

    /** @return array{weekday: int, start: string} a weekly time on another clock, as its next lesson falls there */
    private function onClock(RecurringSlot $slot, string $zone, CarbonImmutable $now): array
    {
        $from = $now->setTimezone($slot->anchor_tz)->startOfDay()->max($slot->startsOn());
        $date = $slot->weeklyTime()->datesBetween($from, $from->addDays(7))[0] ?? $from->toDateString();
        $at = $this->resolver->resolve($date, $slot->weeklyTime()->clock(), $slot->anchor_tz)->utc->setTimezone($zone);

        return ['weekday' => $at->dayOfWeekIso, 'start' => $at->format('H:i')];
    }
}
