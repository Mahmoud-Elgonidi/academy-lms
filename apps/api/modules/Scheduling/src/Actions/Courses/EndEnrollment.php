<?php

namespace Modules\Scheduling\Actions\Courses;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\EndedCourseBilling;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\Dst\PlanDstChanges;
use Modules\Scheduling\Actions\Groups\LeaveGroup;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Support\CourseDays;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Ends a course from a day, its first without lessons (docs/architecture/02,
 * section 3, "إيقاف الكورس وإنهاؤه"), by the office only: its booked lessons
 * from then are cancelled under rule 22 (enrollment_ended: the minutes come
 * back to the balance, which stays as it is; a refund is the academy's to
 * make outside); its weekly times end that day, as ShiftSlotTimes ends a
 * slot, so the teacher's time is free from then; it never renews again
 * (Subscription::taught), and when the day has come its block invoice
 * waiting to be paid is voided if nothing may be on its way (BillingApi;
 * otherwise it stays and the office sees why). A course on a shared package
 * leaves it: the package goes on for the student's other courses. Talking
 * to the teacher ends as for any course that is not running
 * (MessagingRules). The family and the teacher hear it (EnrollmentEnded).
 * An ended course never comes back: a new enrollment is the way back. An
 * end set ahead completes on its day (FollowCourseDays → complete()). A
 * course in a group leaves it on that day (LeaveGroup): its seat is free
 * from then, and the student comes out of the group's lessons from then
 * on; the group's lessons go on for the others.
 */
final class EndEnrollment
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly BillingApi $billing,
        private readonly CreditsApi $credits,
        private readonly CancelSession $cancel,
        private readonly CourseDays $days,
        private readonly PlanDstChanges $dst,
        private readonly LeaveGroup $leave,
    ) {}

    /**
     * What it would do, before anyone confirms: the booked lessons it cancels and
     * their paid minutes coming back, the balance left, and the waiting block invoice.
     *
     * @return array{lessons: Collection<int, Session>, minutes: int, balance: ?int, billing: EndedCourseBilling}
     */
    public function preview(string $enrollmentId, string $on): array
    {
        $course = $this->enrollments->find($enrollmentId) ?? throw ValidationException::withMessages(['enrollment' => __('scheduling::policy.errors.no_enrollment')]);
        $lessons = $this->lessons($course->id, $this->days->startOf(PauseEnrollment::day($on, 'on')));
        $minutes = PauseEnrollment::paidMinutes($lessons);

        return [
            'lessons' => $lessons,
            'minutes' => $minutes,
            'balance' => $course->creditAccountId === null ? null : $this->credits->balance($course->creditAccountId)->remainingMin(),
            'billing' => $this->billing->endingCourse($course->id),
        ];
    }

    /** @return array{cancelled: int, minutes: int, billing: ?EndedCourseBilling} the invoice's fate once the day has come (null: on that day) */
    public function handle(string $enrollmentId, string $on, string $reason, ?string $actorId): array
    {
        $day = PauseEnrollment::day($on, 'on');
        $today = $this->days->today();

        return DB::transaction(function () use ($enrollmentId, $day, $today, $reason, $actorId) {
            // The course's own rules first (not ended already, the day); nothing else happens if they refuse.
            $this->enrollments->end($enrollmentId, $day, $reason, $today, $actorId);
            $endsAt = $this->days->startOf($day);

            $lessons = $this->lessons($enrollmentId, $endsAt);
            $minutes = PauseEnrollment::paidMinutes($lessons);
            $cancelled = $this->cancelFrom($lessons, $actorId);
            $this->leave->handle($enrollmentId, $day, $actorId);
            $this->endSlots($enrollmentId, $endsAt, now: $day->lessThanOrEqualTo($today));
            $billing = $day->lessThanOrEqualTo($today) ? $this->billing->closeEndedCourse($enrollmentId, $actorId) : null;

            app(AuditTrail::class)->record('enrollment.ended', 'enrollment', $enrollmentId, array_filter([
                'on' => $day->toDateString(), 'reason' => trim($reason), 'lessons' => $cancelled, 'minutes' => $minutes,
                'invoice' => $billing?->invoiceNumber, 'invoice_outcome' => $billing?->outcome, 'kept_because' => $billing?->why, 'extra_invoice' => $billing?->extraNumber,
            ], fn ($v) => $v !== null));
            $this->dst->handle(enrollmentIds: [$enrollmentId]);

            return ['cancelled' => $cancelled, 'minutes' => $minutes, 'billing' => $billing];
        });
    }

    /**
     * The day of an end set ahead has come: the course is ended, its weekly times
     * let go, any lesson booked since cancelled, and its waiting block closed.
     */
    public function complete(string $enrollmentId, ?CarbonImmutable $now = null): ?EndedCourseBilling
    {
        $now ??= CarbonImmutable::now();

        return DB::transaction(function () use ($enrollmentId, $now) {
            if ($this->enrollments->settleDays($enrollmentId, $this->days->today($now)) !== EnrollmentStatus::Ended) {
                return null;
            }
            $course = $this->enrollments->find($enrollmentId);
            $endsAt = $this->days->startOf($course?->endsOn ?? $this->days->today($now));
            $this->cancelFrom($this->lessons($enrollmentId, $endsAt), null);
            $this->endSlots($enrollmentId, $endsAt, now: true);
            $billing = $this->billing->closeEndedCourse($enrollmentId, null);
            $this->dst->handle(enrollmentIds: [$enrollmentId]);

            return $billing;
        });
    }

    /** @return Collection<int, Session> */
    private function lessons(string $enrollmentId, CarbonImmutable $endsAt): Collection
    {
        return Session::query()->with('participants')->where('enrollment_id', $enrollmentId)->where('status', SessionStatus::Scheduled->value)
            ->where('starts_at', '>=', $endsAt->max(CarbonImmutable::now()))->orderBy('starts_at')->get();
    }

    /** @param  Collection<int, Session>  $lessons */
    private function cancelFrom(Collection $lessons, ?string $actorId): int
    {
        $cancelled = 0;
        foreach ($lessons as $lesson) {
            try {
                $this->cancel->handle($lesson, 'system', 'enrollment_ended', null, $actorId);
                $cancelled++;
            } catch (ValidationException) {
                continue;   // it began, or changed meanwhile: it stays as it is
            }
        }

        return $cancelled;
    }

    /**
     * Each live weekly time ends where its first lesson at or after the end would
     * be (on its own clock), as ShiftSlotTimes ends a slot; the teacher's time is
     * free from then. Until the day comes it stays live, so its lessons up to the
     * end keep being made.
     */
    private function endSlots(string $enrollmentId, CarbonImmutable $endsAt, bool $now): void
    {
        foreach (RecurringSlot::query()->where('enrollment_id', $enrollmentId)->where('status', 'active')->get() as $slot) {
            $until = $this->days->slotUntil($endsAt, $slot->weeklyTime()->clock(), $slot->anchor_tz);
            DB::update(
                'update recurring_slots set status = ?, updated_at = now(), effective = case when ?::date > lower(effective) '
                ."then daterange(lower(effective), least(coalesce(upper(effective), ?::date), ?::date)) else 'empty'::daterange end where id = ?",
                [$now ? 'ended' : 'active', $until, $until, $until, $slot->id],
            );
        }
    }
}
