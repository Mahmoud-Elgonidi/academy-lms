<?php

namespace Modules\Learning\Domain\Journey;

use Carbon\CarbonImmutable;

/**
 * The stars a lesson brings the student on their journey (docs/architecture/02,
 * section 18): coming in on time (up to three minutes after the start) three,
 * up to ten minutes late two, later still one; and one more for staying to
 * the end (still in the room in its last five minutes). A lesson they missed
 * brings none. A cancelled lesson, the teacher's absence and a lesson still
 * to happen or under review count for nothing yet (null).
 */
final class LessonStars
{
    /** Minutes after the start that are still on time. */
    public const ON_TIME_MIN = 3;

    /** Minutes after the start that are a little late (two stars). */
    public const LATE_MIN = 10;

    /** Leaving no earlier than this before the end is staying to the end. */
    public const END_GRACE_MIN = 5;

    public const MAX = 4;

    /**
     * @param  string  $status  the lesson's status (scheduled, in_progress, completed, student_absent, teacher_absent, needs_review, cancelled)
     * @return ?array{stars: int, late_min: ?int, on_time: bool, stayed: bool} null while the lesson does not count (yet)
     */
    public static function for(string $status, CarbonImmutable $startsAt, ?CarbonImmutable $endsAt, ?CarbonImmutable $joinedAt, ?CarbonImmutable $lastLeftAt): ?array
    {
        if ($status === 'student_absent') {
            return ['stars' => 0, 'late_min' => null, 'on_time' => false, 'stayed' => false];
        }
        if ($status !== 'completed') {
            return null;
        }
        if ($joinedAt === null) {
            // Taught, but nobody saw the student come in (marked by hand): the lesson counts, without lateness.
            return ['stars' => 1, 'late_min' => null, 'on_time' => false, 'stayed' => false];
        }
        $late = max(0, (int) floor(($joinedAt->getTimestamp() - $startsAt->getTimestamp()) / 60));
        $base = match (true) {
            $late <= self::ON_TIME_MIN => 3,
            $late <= self::LATE_MIN => 2,
            default => 1,
        };
        $stayed = $endsAt !== null && ($lastLeftAt === null || $lastLeftAt->greaterThanOrEqualTo($endsAt->subMinutes(self::END_GRACE_MIN)));

        return ['stars' => $base + ($stayed ? 1 : 0), 'late_min' => $late, 'on_time' => $late <= self::ON_TIME_MIN, 'stayed' => $stayed];
    }
}
