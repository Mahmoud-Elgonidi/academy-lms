<?php

namespace Modules\Scheduling\Actions\Courses;

use Carbon\CarbonImmutable;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Scheduling\Support\CourseDays;
use Throwable;

/**
 * Courses whose days come (docs/architecture/02, section 3, "إيقاف الكورس
 * وإنهاؤه"), on the academy's calendar, looked at every hour so a day
 * begins within the hour of its midnight: an end set ahead completes
 * (EndEnrollment::complete), a pause over comes back by itself
 * (ResumeEnrollment, no one acting), a pause that begins makes the course
 * paused. One course that fails does not hold back the others.
 */
final class FollowCourseDays
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly EndEnrollment $end,
        private readonly ResumeEnrollment $resume,
        private readonly CourseDays $days,
    ) {}

    /** @return int courses that moved */
    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $today = $this->days->today($now);
        $day = $today->toDateString();
        $moved = 0;

        foreach ($this->enrollments->withDays() as $course) {
            try {
                if ($course->endsOn !== null && $course->endsOn->toDateString() <= $day) {
                    $this->end->complete($course->id, $now);
                    $moved++;
                } elseif ($course->pausedUntil !== null && $course->pausedUntil->toDateString() < $day) {
                    $this->resume->handle($course->id, null, $now);
                    $moved++;
                } elseif ($course->status === EnrollmentStatus::Active && $course->pausedOn($day)) {
                    $moved += $this->enrollments->settleDays($course->id, $today) !== null ? 1 : 0;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $moved;
    }
}
