<?php

namespace Modules\Scheduling\Queries\Groups;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Domain\Models\GroupMember;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Support\CourseDays;
use Modules\SharedKernel\Time\LocalTimeResolver;

/**
 * A learner's groups as their family reads them (/my, docs/architecture/02,
 * "الحصص الجماعية"): the subject, the teacher's name, the weekly times on
 * the viewer's clock, and their own seat ("seat 4 of 5": the order members
 * took their seats in). Never a classmate's name or contact: the lesson
 * cards say how many are with them.
 */
final class LearnerGroups
{
    public function __construct(
        private readonly CourseDays $days,
        private readonly LocalTimeResolver $resolver,
    ) {}

    /** @return list<array{id: string, subject: string, teacher: string, times: list<array{weekday: int, start: string}>, seat: int, capacity: int, taken: int, status: string, waits: ?int}> */
    public function of(string $studentId, string $zone, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $today = $this->days->today($now)->toDateString();
        $mine = GroupMember::query()->where('student_id', $studentId)
            ->where(fn ($q) => $q->whereNull('left_on')->orWhere('left_on', '>', $today))
            ->with('group')->get()
            ->filter(fn (GroupMember $m) => $m->group !== null && $m->group->status !== LessonGroup::ENDED);
        if ($mine->isEmpty()) {
            return [];
        }
        $ids = $mine->pluck('group_id')->unique()->values()->all();
        $seated = GroupMember::query()->whereIn('group_id', $ids)
            ->where(fn ($q) => $q->whereNull('left_on')->orWhere('left_on', '>', $today))
            ->orderBy('joined_on')->orderBy('created_at')->orderBy('id')->get(['id', 'group_id'])->groupBy('group_id');
        $slots = RecurringSlot::query()->whereIn('group_id', $ids)->where('status', 'active')->get()->groupBy('group_id');
        $subjects = DB::table('subjects')->pluck(app()->getLocale() === 'ar' ? 'name_ar' : 'name_en', 'id');
        $teachers = DB::table('users')->whereIn('id', $mine->pluck('group.teacher_id')->unique()->all())->pluck('name', 'id');

        return $mine->map(function (GroupMember $m) use ($seated, $slots, $subjects, $teachers, $zone, $now) {
            $g = $m->group;
            $members = $seated->get($g->id, collect())->pluck('id')->values();
            $waits = $g->status === LessonGroup::FORMING && $g->min_to_start !== null ? max(0, $g->min_to_start - $members->count()) : null;

            return [
                'id' => $g->id,
                'subject' => (string) ($subjects[$g->subject_id] ?? ''),
                'teacher' => (string) ($teachers[$g->teacher_id] ?? ''),
                'times' => $slots->get($g->id, collect())->map(fn (RecurringSlot $s) => GroupBoard::onClock($this->resolver, $s, $zone, $now))->values()->all(),
                'seat' => (int) $members->search($m->id) + 1,
                'capacity' => $g->capacity,
                'taken' => $members->count(),
                'status' => $g->status,
                'waits' => $waits ?: null,
            ];
        })->values()->all();
    }
}
