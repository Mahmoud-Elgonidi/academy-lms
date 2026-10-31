<?php

namespace Modules\Billing\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Which of a learner's courses are seats in a group (docs/architecture/02,
 * section 25), for the family's balance: the group, its seats, how many are
 * taken and the learner's own seat (the order members took theirs). A read
 * of the groups' tables, as Scheduling's CourseGroups answers it (Billing
 * sits below Scheduling, so it reads rather than asks).
 */
final class GroupSeats
{
    /**
     * @param  list<string>  $enrollmentIds
     * @return array<string, array{id: string, capacity: int, seats_taken: int, seat: int}> by enrollment id; a private course is left out
     */
    public static function of(array $enrollmentIds, ?CarbonImmutable $now = null): array
    {
        if ($enrollmentIds === []) {
            return [];
        }
        $today = ($now ?? CarbonImmutable::now())->toDateString();
        $live = '(o.left_on is null or o.left_on > ?::date)';

        return DB::table('group_members as m')
            ->join('lesson_groups as g', fn ($j) => $j->on('g.academy_id', '=', 'm.academy_id')->on('g.id', '=', 'm.group_id'))
            ->whereIn('m.enrollment_id', array_values($enrollmentIds))
            ->where(fn ($q) => $q->whereNull('m.left_on')->orWhere('m.left_on', '>', $today))
            ->select(['m.enrollment_id', 'g.id', 'g.capacity'])
            ->selectRaw("(select count(*) from group_members o where o.academy_id = m.academy_id and o.group_id = m.group_id and {$live}) as seats_taken", [$today])
            ->selectRaw("(select count(*) from group_members o where o.academy_id = m.academy_id and o.group_id = m.group_id and {$live}
                and (o.joined_on, o.created_at, o.id) <= (m.joined_on, m.created_at, m.id)) as seat", [$today])
            ->get()
            ->mapWithKeys(fn (object $r) => [(string) $r->enrollment_id => [
                'id' => (string) $r->id, 'capacity' => (int) $r->capacity, 'seats_taken' => (int) $r->seats_taken, 'seat' => max(1, (int) $r->seat),
            ]])->all();
    }
}
