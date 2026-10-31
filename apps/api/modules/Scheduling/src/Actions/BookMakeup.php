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
use Modules\Scheduling\Support\CourseDays;
use Modules\Scheduling\Support\MinorsGuard;

/**
 * Books a makeup session on a free time of the enrollment's teacher, paid
 * from makeup credit only (docs/architecture/01, "رصيد تعويض"): it must be
 * valid on that day, and a makeup that is then cancelled is not made up again.
 * Not on a paused day of the course, nor once it has ended (CourseDays).
 * Makeup credit kept for group makeup lessons (a late join's) pays only for
 * BookGroupMakeup; the rest pays for this.
 */
final class BookMakeup
{
    public function __construct(
        private readonly ScheduleSession $schedule,
        private readonly CreditsApi $credits,
        private readonly EnrollmentApi $enrollments,
        private readonly AvailabilityQuery $availability,
        private readonly MinorsGuard $minors,
        private readonly CourseDays $days,
    ) {}

    public function handle(string $enrollmentId, CarbonImmutable $startsAt, ?string $actorId, ?string $makeupFor = null): Session
    {
        $enrollment = $this->enrollments->find($enrollmentId);
        if ($enrollment === null || $enrollment->creditAccountId === null) {
            throw ValidationException::withMessages(['enrollment' => __('scheduling::policy.errors.no_makeup_credit')]);
        }

        $this->minors->assert($enrollment->teacherId, $enrollment->studentId);
        // A course in a group has no weekly times of its own: its lessons are the group's length.
        $duration = (int) (DB::table('recurring_slots')->where('enrollment_id', $enrollmentId)->value('duration_min')
            ?? DB::table('group_members as m')->join('lesson_groups as g', fn ($j) => $j->on('g.academy_id', '=', 'm.academy_id')->on('g.id', '=', 'm.group_id'))
                ->where('m.enrollment_id', $enrollmentId)->orderByDesc('m.joined_on')->value('g.session_minutes')
            ?? 30);
        $startsAt = $startsAt->utc();
        $endsAt = $startsAt->addMinutes($duration);
        if ($startsAt->isPast()) {
            throw ValidationException::withMessages(['starts_at' => __('scheduling::policy.errors.in_the_past')]);
        }
        $this->days->assertOpen($enrollment, $startsAt);

        $teacher = $this->availability->forTeachers([$enrollment->teacherId], $startsAt->subHour(), $endsAt->addHour())[$enrollment->teacherId] ?? null;
        if ($teacher === null || ! $teacher->free->contains($startsAt->getTimestamp(), $endsAt->getTimestamp())) {
            throw ValidationException::withMessages(['starts_at' => __('scheduling::policy.errors.teacher_busy')]);
        }
        $teacherTz = DB::table('users')->where('id', $enrollment->teacherId)->value('timezone') ?? 'UTC';

        return DB::transaction(function () use ($enrollment, $startsAt, $duration, $teacherTz, $actorId, $makeupFor) {
            $session = $this->schedule->handle(new NewSession(
                teacherId: $enrollment->teacherId,
                studentId: $enrollment->studentId,
                subjectId: $enrollment->subjectId,
                startsAt: $startsAt,
                durationMin: $duration,
                anchorTimezone: $teacherTz,
                type: 'makeup',
                funding: 'unfunded',
                enrollmentId: $enrollment->id,
            ), $actorId);

            if (! $this->credits->reserve($session->id, $enrollment->creditAccountId, $duration, $startsAt, "session:{$session->id}:reserve", forMakeup: true)) {
                throw ValidationException::withMessages(['starts_at' => __('scheduling::policy.errors.no_makeup_credit')]);
            }

            $session->funding_status = FundingStatus::Makeup;
            $session->makeup_for_session_id = $makeupFor;
            $session->save();

            return $session;
        });
    }
}
