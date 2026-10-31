<?php

namespace Modules\Scheduling\Contracts;

/**
 * Which courses are seats in a group (docs/architecture/02, section 25), for
 * the screens that show a learner's courses: the group, its seats, how many
 * are taken and the learner's own seat (the order members took theirs).
 */
interface CourseGroups
{
    /**
     * @param  list<string>  $enrollmentIds
     * @return array<string, array{id: string, capacity: int, seats_taken: int, seat: int, status: string}> by enrollment id; a private course is left out
     */
    public function of(array $enrollmentIds): array;
}
