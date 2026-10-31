<?php

namespace Modules\Scheduling\Queries\Groups;

use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Contracts\CourseGroups;
use Modules\Scheduling\Support\CourseDays;

/** A course's seat in its group, read each time: the seats taken today and the member's place among them by when they joined. */
final class CourseGroupsQuery implements CourseGroups
{
    public function __construct(private readonly CourseDays $days) {}

    public function of(array $enrollmentIds): array
    {
        return self::read($enrollmentIds, $this->days->today()->toDateString());
    }

    /** @return array<string, array{id: string, capacity: int, seats_taken: int, seat: int, status: string}> */
    public static function read(array $enrollmentIds, string $today): array
    {
        if ($enrollmentIds === []) {
            return [];
        }
        $live = '(o.left_on is null or o.left_on > ?::date)';

        return DB::table('group_members as m')
            ->join('lesson_groups as g', fn ($j) => $j->on('g.academy_id', '=', 'm.academy_id')->on('g.id', '=', 'm.group_id'))
            ->whereIn('m.enrollment_id', array_values($enrollmentIds))
            ->where(fn ($q) => $q->whereNull('m.left_on')->orWhere('m.left_on', '>', $today))
            ->select(['m.enrollment_id', 'g.id', 'g.capacity', 'g.status'])
            ->selectRaw("(select count(*) from group_members o where o.academy_id = m.academy_id and o.group_id = m.group_id and {$live}) as seats_taken", [$today])
            ->selectRaw("(select count(*) from group_members o where o.academy_id = m.academy_id and o.group_id = m.group_id and {$live}
                and (o.joined_on, o.created_at, o.id) <= (m.joined_on, m.created_at, m.id)) as seat", [$today])
            ->get()
            ->mapWithKeys(fn (object $r) => [(string) $r->enrollment_id => [
                'id' => (string) $r->id, 'capacity' => (int) $r->capacity, 'seats_taken' => (int) $r->seats_taken, 'seat' => (int) $r->seat, 'status' => (string) $r->status,
            ]])->all();
    }
}
