<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Scheduling\Contracts\DTOs\AssessmentBooking;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Domain\Enums\SessionType;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Support\CourseDays;
use Modules\Scheduling\Support\MinorsGuard;

/**
 * Books an assessment lesson for a course (Contracts\AssessmentLessons):
 * its examiner as the lesson's teacher and the course's student, free of
 * the course's minutes, only while the course makes lessons and not on a
 * day it is paused or after it ends (CourseDays), never with an examiner
 * People does not clear for a student under 18 (MinorsGuard). The database
 * refuses anyone booked twice at once.
 */
final class BookAssessmentLesson
{
    public const MIN_MINUTES = 10;

    public const MAX_MINUTES = 120;

    public function __construct(
        private readonly ScheduleSession $schedule,
        private readonly EnrollmentApi $enrollments,
        private readonly MinorsGuard $minors,
        private readonly CourseDays $days,
    ) {}

    public function handle(AssessmentBooking $booking): Session
    {
        $course = $this->enrollments->find($booking->enrollmentId);
        if ($course === null) {
            throw ValidationException::withMessages(['enrollment' => __('scheduling::policy.errors.no_enrollment')]);
        }
        if (! $course->status->makesLessons()) {
            throw ValidationException::withMessages(['enrollment' => __('scheduling::policy.errors.course_not_running')]);
        }
        if ($booking->durationMin < self::MIN_MINUTES || $booking->durationMin > self::MAX_MINUTES) {
            throw ValidationException::withMessages(['duration_min' => __('scheduling::policy.errors.assessment_length')]);
        }
        $startsAt = $booking->startsAt->utc();
        if ($startsAt->isPast()) {
            throw ValidationException::withMessages(['starts_at' => __('scheduling::policy.errors.in_the_past')]);
        }
        $this->minors->assert($booking->examinerId, $course->studentId);
        $this->days->assertOpen($course, $startsAt);
        $timezone = DB::table('users')->where('id', $booking->examinerId)->value('timezone') ?? 'UTC';

        return $this->schedule->handle(new NewSession(
            teacherId: $booking->examinerId,
            studentId: $course->studentId,
            subjectId: $course->subjectId,
            startsAt: $startsAt,
            durationMin: $booking->durationMin,
            anchorTimezone: (string) $timezone,
            type: SessionType::Assessment->value,
            funding: 'free',
            enrollmentId: $course->id,
        ), $booking->actorId);
    }
}
