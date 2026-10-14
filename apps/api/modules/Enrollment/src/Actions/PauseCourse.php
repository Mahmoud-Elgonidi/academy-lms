<?php

namespace Modules\Enrollment\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Enrollment\Contracts\Events\EnrollmentPaused;
use Modules\Enrollment\Models\Enrollment;

/**
 * Puts a pause on a running course (docs/architecture/02, section 3,
 * "إيقاف الكورس وإنهاؤه"): from a day to a day on the academy's calendar,
 * both included, from today on. The course is paused now when the first
 * day has come, else from it (SettleCourseDays). A pause already set may
 * only be made longer (the same first day, a later last one); to change it
 * otherwise the office resumes the course and pauses it again. A course
 * that is ending, or waiting for its first payment, is not paused. The
 * lessons, the credit and the family's message are the caller's.
 */
final class PauseCourse
{
    /** The longest pause at a time, in days. */
    public const MAX_DAYS = 120;

    /** @return EnrollmentData the course as it was before */
    public function handle(string $enrollmentId, CarbonImmutable $from, CarbonImmutable $until, string $reason, CarbonImmutable $today, ?string $actorId): EnrollmentData
    {
        $l = 'enrollment::enrollment.errors.';
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __($l.'reason')]);
        }

        return DB::transaction(function () use ($enrollmentId, $from, $until, $reason, $today, $actorId, $l) {
            $enrollment = Enrollment::query()->whereKey($enrollmentId)->lockForUpdate()->firstOrFail();
            $before = $enrollment->toData();
            [$from, $until, $today] = [$from->startOfDay(), $until->startOfDay(), $today->startOfDay()];

            if (! in_array($enrollment->status, [EnrollmentStatus::Active, EnrollmentStatus::Paused], true)) {
                throw ValidationException::withMessages(['enrollment' => __($l.'not_pausable.'.$enrollment->status->value)]);
            }
            if ($enrollment->ends_on !== null) {
                throw ValidationException::withMessages(['enrollment' => __($l.'ending', ['date' => $enrollment->ends_on->toDateString()])]);
            }
            if ($until->lessThan($from)) {
                throw ValidationException::withMessages(['until' => __($l.'pause_dates')]);
            }
            if ($from->diffInDays($until) + 1 > self::MAX_DAYS) {
                throw ValidationException::withMessages(['until' => __($l.'pause_too_long', ['days' => self::MAX_DAYS])]);
            }
            if ($enrollment->paused_from !== null) {
                // Made longer only: the same first day (it may have begun), a later last one.
                if (! $from->equalTo($enrollment->paused_from) || ! $until->greaterThan($enrollment->paused_until)) {
                    throw ValidationException::withMessages(['until' => __($l.'pause_longer', ['date' => $enrollment->paused_until->toDateString()])]);
                }
            } elseif ($from->lessThan($today)) {
                throw ValidationException::withMessages(['from' => __($l.'pause_past')]);
            }

            $enrollment->paused_from = $from;
            $enrollment->paused_until = $until;
            $enrollment->pause_reason = $reason;
            $enrollment->save();
            if ($enrollment->status === EnrollmentStatus::Active && $from->lessThanOrEqualTo($today)) {
                $enrollment->moveTo(EnrollmentStatus::Paused, 'pause', $actorId);
            }

            DB::afterCommit(fn () => event(new EnrollmentPaused($enrollment->academy_id, $enrollment->id, $enrollment->student_id, $from->toDateString(), $until->toDateString(), $actorId)));

            return $before;
        });
    }
}
