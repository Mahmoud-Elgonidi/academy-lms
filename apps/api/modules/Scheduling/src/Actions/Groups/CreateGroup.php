<?php

namespace Modules\Scheduling\Actions\Groups;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\People\Contracts\AgeGroup;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\CreateRecurringSlots;
use Modules\Scheduling\Contracts\DTOs\NewGroup;
use Modules\Scheduling\Domain\Groups\GroupRules;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Domain\Models\SchedulingSettings;
use Modules\Scheduling\Domain\Slots\WeeklyTime;
use Modules\Scheduling\Support\CourseDays;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Opens a group with a teacher who takes groups (docs/architecture/02,
 * "الحصص الجماعية"): its seats (at most the teacher's), the students it
 * waits for, its subject, level and ages, and its weekly times on the
 * teacher's clock, booked as the group's own (the database refuses one that
 * overlaps the teacher's other times). It waits as forming until its
 * students come (JoinGroup starts it); no lessons are made before.
 */
final class CreateGroup
{
    public function __construct(
        private readonly PeopleApi $people,
        private readonly CreateRecurringSlots $slots,
        private readonly CourseDays $days,
    ) {}

    public function handle(NewGroup $new, ?string $actorId): LessonGroup
    {
        $teacherMax = $this->people->groupCapacity($new->teacherId);
        $capacity = $new->capacity ?? $teacherMax ?? 0;
        $minToStart = $new->minToStart ?? SchedulingSettings::current()->group_min_default;
        if ($minToStart !== null && $new->minToStart === null) {
            $minToStart = min($minToStart, $capacity);     // the academy's default never asks for more students than the seats
        }
        self::refuse('capacity', GroupRules::capacity($capacity, $teacherMax), ['max' => $teacherMax]);
        self::refuse('min_to_start', GroupRules::minToStart($minToStart, $capacity), ['capacity' => $capacity]);
        if ($new->ageGroup !== null && AgeGroup::tryFrom($new->ageGroup) === null) {
            self::refuse('age_group', 'age_group');
        }
        if ($new->sessionMinutes <= 0 || $new->sessionMinutes > 240) {
            self::refuse('session_minutes', 'session_minutes');
        }

        $tz = DB::table('users')->where('id', $new->teacherId)->value('timezone') ?? 'UTC';
        $from = ($new->startsOn ?? $this->days->today())->startOfDay();
        $times = [];
        foreach ($new->times as $time) {
            $start = substr((string) ($time['start'] ?? ''), 0, 5);
            $weekday = (int) ($time['weekday'] ?? 0);
            if ($weekday < 1 || $weekday > 7 || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start) !== 1) {
                self::refuse('times', 'bad_time');
            }
            if (isset($times[$weekday.'|'.$start])) {
                self::refuse('times', 'time_twice');
            }
            $times[$weekday.'|'.$start] = ['time' => WeeklyTime::at($weekday, $start, $new->sessionMinutes), 'from' => $from];
        }
        if ($times === []) {
            self::refuse('times', 'no_times');
        }

        return DB::transaction(function () use ($new, $actorId, $capacity, $minToStart, $tz, $times) {
            $group = LessonGroup::query()->create([
                'teacher_id' => $new->teacherId,
                'subject_id' => $new->subjectId,
                'level_id' => $new->levelId,
                'age_group' => $new->ageGroup,
                'capacity' => $capacity,
                'min_to_start' => $minToStart,
                'session_minutes' => $new->sessionMinutes,
                'sessions_per_week' => count($times),
                'anchor_tz' => $tz,
                'status' => LessonGroup::FORMING,
                'name' => filled($new->name) ? mb_substr(trim((string) $new->name), 0, 120) : null,
                'created_by' => $actorId,
            ]);
            $this->slots->handle(null, $new->teacherId, null, $tz, $times, $actorId, groupId: $group->id);

            app(AuditTrail::class)->record('lesson_group.created', 'lesson_group', $group->id, array_filter([
                'capacity' => $capacity, 'min_to_start' => $minToStart, 'per_week' => count($times), 'minutes' => $new->sessionMinutes,
            ], fn ($v) => $v !== null));

            return $group;
        });
    }

    /** @param  array<string, mixed>  $params */
    public static function refuse(string $field, ?string $why, array $params = []): void
    {
        if ($why !== null) {
            throw ValidationException::withMessages([$field => __('scheduling::groups.errors.'.$why, $params)]);
        }
    }
}
