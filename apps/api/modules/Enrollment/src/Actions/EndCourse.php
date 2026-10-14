<?php

namespace Modules\Enrollment\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Enrollment\Contracts\Events\EnrollmentEnded;
use Modules\Enrollment\Models\Enrollment;

/**
 * Ends a course from a day (its first day without lessons, on the
 * academy's calendar, today or later): ended now when that day has come,
 * else on it (SettleCourseDays). Running, paused or waiting for its first
 * payment, any course may end; an ended one never comes back. A pause
 * that would only begin after the end is dropped. The lessons, the weekly
 * times, the money and the family's message are the caller's.
 */
final class EndCourse
{
    /** How far ahead an end may be set, in days. */
    public const MAX_DAYS_AHEAD = 366;

    /** @return EnrollmentData the course as it was before */
    public function handle(string $enrollmentId, CarbonImmutable $on, string $reason, CarbonImmutable $today, ?string $actorId): EnrollmentData
    {
        $l = 'enrollment::enrollment.errors.';
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __($l.'reason')]);
        }

        return DB::transaction(function () use ($enrollmentId, $on, $reason, $today, $actorId, $l) {
            $enrollment = Enrollment::query()->whereKey($enrollmentId)->lockForUpdate()->firstOrFail();
            $before = $enrollment->toData();
            [$on, $today] = [$on->startOfDay(), $today->startOfDay()];

            if ($enrollment->status === EnrollmentStatus::Ended) {
                throw ValidationException::withMessages(['enrollment' => __($l.'ended')]);
            }
            if ($enrollment->ends_on !== null) {
                throw ValidationException::withMessages(['enrollment' => __($l.'ending', ['date' => $enrollment->ends_on->toDateString()])]);
            }
            if ($on->lessThan($today) || $on->greaterThan($today->addDays(self::MAX_DAYS_AHEAD))) {
                throw ValidationException::withMessages(['on' => __($l.'end_day')]);
            }

            $enrollment->ends_on = $on;
            $enrollment->end_reason = $reason;
            if ($enrollment->paused_from !== null && $enrollment->paused_from->greaterThanOrEqualTo($on)) {
                $enrollment->clearPause();
            }
            $enrollment->save();
            if ($on->lessThanOrEqualTo($today)) {
                $enrollment->moveTo(EnrollmentStatus::Ended, 'end', $actorId);
            }

            DB::afterCommit(fn () => event(new EnrollmentEnded($enrollment->academy_id, $enrollment->id, $enrollment->student_id, $on->toDateString(), $actorId)));

            return $before;
        });
    }
}
