<?php

namespace Modules\Enrollment\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Enrollment\Models\Enrollment;

/**
 * A course's status as its days say today (on the academy's calendar): a
 * course whose end has come is ended; a running course whose pause has
 * begun is paused. Coming back after a pause goes through ResumeCourse
 * (its lessons are made again), so it is not done here.
 */
final class SettleCourseDays
{
    /** @return ?EnrollmentStatus the new status, when it moved */
    public function handle(string $enrollmentId, CarbonImmutable $today): ?EnrollmentStatus
    {
        return DB::transaction(function () use ($enrollmentId, $today) {
            $enrollment = Enrollment::query()->whereKey($enrollmentId)->lockForUpdate()->firstOrFail();
            $day = $today->toDateString();

            if ($enrollment->status !== EnrollmentStatus::Ended && $enrollment->ends_on !== null && $enrollment->ends_on->toDateString() <= $day) {
                $enrollment->moveTo(EnrollmentStatus::Ended, 'end', null);

                return EnrollmentStatus::Ended;
            }
            if ($enrollment->status === EnrollmentStatus::Active && $enrollment->paused_from !== null
                && $enrollment->paused_from->toDateString() <= $day && $enrollment->paused_until->toDateString() >= $day) {
                $enrollment->moveTo(EnrollmentStatus::Paused, 'pause', null);

                return EnrollmentStatus::Paused;
            }

            return null;
        });
    }
}
