<?php

namespace Modules\Scheduling\Actions\Courses;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Scheduling\Actions\Dst\PlanDstChanges;
use Modules\Scheduling\Actions\FundSessions;
use Modules\Scheduling\Actions\Groups\GroupSeats;
use Modules\Scheduling\Actions\Groups\LeaveGroup;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Support\CourseDays;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * A paused course is on again (docs/architecture/02, section 3, "إيقاف
 * الكورس وإنهاؤه"): by itself on the day after its pause (FollowCourseDays,
 * no one acting), or earlier when the office says so; a pause still ahead
 * is called off. Its lessons are made again from now: the ones the pause
 * cancelled from now on give their dates back (their weekly occurrence is
 * free to be made again), the weekly times become lessons up to the
 * horizon, and the balance funds them in date order. The credit keeps the
 * days the pause gave it. The family and the teacher hear it (EnrollmentResumed).
 * A course in a group takes its seat again in the group's lessons from now.
 */
final class ResumeEnrollment
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly MaterializeSessions $materialize,
        private readonly FundSessions $fund,
        private readonly CourseDays $days,
        private readonly PlanDstChanges $dst,
        private readonly GroupSeats $seats,
    ) {}

    /** @return int lessons made from now */
    public function handle(string $enrollmentId, ?string $actorId, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $course = $this->enrollments->find($enrollmentId) ?? throw ValidationException::withMessages(['enrollment' => __('scheduling::policy.errors.no_enrollment')]);
        $today = $this->days->today($now);
        $early = $course->pausedUntil === null || $today->lessThanOrEqualTo($course->pausedUntil);

        return DB::transaction(function () use ($course, $actorId, $now, $early) {
            $this->enrollments->resume($course->id, $early, $actorId);

            // The paused days still to come have their lessons again: their dates were held by the cancelled ones.
            Session::query()->where('enrollment_id', $course->id)->where('status', SessionStatus::Cancelled->value)
                ->whereRaw("decision->>'reason' = 'enrollment_paused'")->where('starts_at', '>', $now)->whereNotNull('occurrence_key')
                ->update(['occurrence_key' => null]);
            $made = count($this->materialize->handle([$course->id], $now, $actorId)['created']);
            if (($member = LeaveGroup::memberOf($course->id)) !== null) {
                $made += $this->seats->seatFrom($member, $now, null, $actorId);
            }
            $this->fund->handle($course->id, $now);

            app(AuditTrail::class)->record('enrollment.resumed', 'enrollment', $course->id, array_filter([
                'until' => $course->pausedUntil?->toDateString(), 'early' => $early ? 'early' : 'on_time', 'lessons' => $made,
            ], fn ($v) => $v !== null));
            $this->dst->handle(enrollmentIds: [$course->id]);

            return $made;
        });
    }
}
