<?php

namespace Modules\Scheduling\Queries\Start;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\SharedKernel\Time\LocalTimeResolver;

/**
 * A course's weekly times as instants (each one's next lesson from a moment),
 * so a family reads them on its own clock: the course's own weekly times, or
 * its group's. Its first lesson booked, if any.
 */
final class CourseTimes
{
    public function __construct(private readonly LocalTimeResolver $resolver) {}

    /** @return list<CarbonImmutable> one instant per live weekly time, the next on or after $from, soonest first */
    public function weekly(string $enrollmentId, ?CarbonImmutable $from = null): array
    {
        $from ??= CarbonImmutable::now();
        $groupId = DB::table('group_members')->where('enrollment_id', $enrollmentId)->value('group_id');
        $slots = RecurringSlot::query()->where('status', 'active')
            ->where(fn ($q) => $q->where('enrollment_id', $enrollmentId)->when($groupId !== null, fn ($w) => $w->orWhere('group_id', $groupId)))->get();
        $out = [];
        foreach ($slots as $slot) {
            $start = $from->setTimezone($slot->anchor_tz)->startOfDay()->max($slot->startsOn());
            foreach ($slot->weeklyTime()->datesBetween($start, $start->addDays(8)) as $date) {
                $at = $this->resolver->resolve($date, $slot->weeklyTime()->clock(), $slot->anchor_tz)->utc;
                if ($at->greaterThanOrEqualTo($from)) {
                    $out[] = $at;
                    break;
                }
            }
        }
        usort($out, fn (CarbonImmutable $a, CarbonImmutable $b) => $a <=> $b);

        return $out;
    }

    /** The course's first lesson still to come (its own, or its seat in a group's), if one is booked. */
    public function firstLesson(string $enrollmentId, ?CarbonImmutable $from = null): ?array
    {
        $row = DB::table('sessions as s')
            ->where('s.status', 'scheduled')->where('s.starts_at', '>', $from ?? CarbonImmutable::now())
            ->where(fn ($q) => $q->where('s.enrollment_id', $enrollmentId)->orWhereExists(fn ($p) => $p->from('session_participants as p')
                ->whereColumn('p.academy_id', 's.academy_id')->whereColumn('p.session_id', 's.id')->where('p.enrollment_id', $enrollmentId)->where('p.is_active', true)))
            ->orderBy('s.starts_at')->first(['s.id', 's.starts_at']);

        return $row === null ? null : ['id' => (string) $row->id, 'at' => CarbonImmutable::parse($row->starts_at)];
    }
}
