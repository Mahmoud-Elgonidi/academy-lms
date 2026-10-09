<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Queries\AvailabilityQuery;
use Modules\Scheduling\Support\MinorsGuard;

/**
 * Books one lesson outside the weekly times (an extra lesson the family
 * bought, or one the course's balance has room for) at a free time of the
 * course's teacher, paid from the course's own minutes (not makeup credit),
 * valid on that day. It is a lesson like the others for cancelling and
 * attendance.
 */
final class BookExtraLesson
{
    public function __construct(
        private readonly ScheduleSession $schedule,
        private readonly CreditsApi $credits,
        private readonly EnrollmentApi $enrollments,
        private readonly AvailabilityQuery $availability,
        private readonly MinorsGuard $minors,
    ) {}

    public function handle(string $enrollmentId, CarbonImmutable $startsAt, ?string $actorId): Session
    {
        $enrollment = $this->enrollments->find($enrollmentId);
        if ($enrollment === null || $enrollment->creditAccountId === null || $enrollment->status->value !== 'active') {
            throw ValidationException::withMessages(['enrollment' => __('scheduling::policy.errors.no_extra_credit')]);
        }

        $this->minors->assert($enrollment->teacherId, $enrollment->studentId);
        $duration = (int) (DB::table('recurring_slots')->where('enrollment_id', $enrollmentId)->value('duration_min') ?? 30);
        $startsAt = $startsAt->utc();
        $endsAt = $startsAt->addMinutes($duration);
        if ($startsAt->isPast()) {
            throw ValidationException::withMessages(['starts_at' => __('scheduling::policy.errors.in_the_past')]);
        }

        $teacher = $this->availability->forTeachers([$enrollment->teacherId], $startsAt->subHour(), $endsAt->addHour())[$enrollment->teacherId] ?? null;
        if ($teacher === null || ! $teacher->free->contains($startsAt->getTimestamp(), $endsAt->getTimestamp())) {
            throw ValidationException::withMessages(['starts_at' => __('scheduling::policy.errors.teacher_busy')]);
        }
        $teacherTz = DB::table('users')->where('id', $enrollment->teacherId)->value('timezone') ?? 'UTC';

        return DB::transaction(function () use ($enrollment, $startsAt, $duration, $teacherTz, $actorId) {
            $session = $this->schedule->handle(new NewSession(
                teacherId: $enrollment->teacherId,
                studentId: $enrollment->studentId,
                subjectId: $enrollment->subjectId,
                startsAt: $startsAt,
                durationMin: $duration,
                anchorTimezone: $teacherTz,
                type: 'extra',
                funding: 'unfunded',
                enrollmentId: $enrollment->id,
            ), $actorId);

            if (! $this->credits->reserve($session->id, $enrollment->creditAccountId, $duration, $startsAt, "session:{$session->id}:reserve")) {
                throw ValidationException::withMessages(['starts_at' => __('scheduling::policy.errors.no_extra_credit')]);
            }

            $session->funding_status = FundingStatus::Funded;
            $session->save();

            return $session;
        });
    }
}
