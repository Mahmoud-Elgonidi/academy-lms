<?php

namespace Modules\Scheduling\Actions\Courses;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\Dst\PlanDstChanges;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Support\CourseDays;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Pauses a course for a holiday, exams, travel (docs/architecture/02,
 * section 3, "إيقاف الكورس وإنهاؤه"), from a day to a day on the academy's
 * calendar, by the office only. Its booked lessons in those days are
 * cancelled under rule 22 (enrollment_paused: the minutes come back to the
 * balance, not makeup, not lost); the credit lasts as many days longer
 * (CreditsApi::extend, first, so nothing lapses meanwhile; not when another
 * course still draws on a shared balance); the weekly times stay the
 * student's, and no lessons are made for those days (MaterializeSessions);
 * nothing renews while it is paused (Subscription::taught). A pause already
 * set may be made longer: only the added days are cancelled and given back.
 * The course comes back by itself the day after (ResumeEnrollment through
 * FollowCourseDays), or earlier when the office resumes it. The family and
 * the teacher hear it once (EnrollmentPaused), not once a lesson.
 */
final class PauseEnrollment
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly CreditsApi $credits,
        private readonly CancelSession $cancel,
        private readonly CourseDays $days,
        private readonly PlanDstChanges $dst,
    ) {}

    /**
     * What it would do, before anyone confirms: the booked lessons it cancels,
     * their paid minutes coming back, and the days the credit lasts longer.
     *
     * @return array{lessons: Collection<int, Session>, minutes: int, days: int, sharing: bool}
     */
    public function preview(string $enrollmentId, string $from, string $until): array
    {
        $course = $this->course($enrollmentId);
        [$first, $last] = [self::day($from, 'from'), self::day($until, 'until')];
        if ($last->lessThan($first)) {
            return ['lessons' => collect(), 'minutes' => 0, 'days' => 0, 'sharing' => false];
        }
        $lessons = $this->lessons($course, $first, $last);

        return ['lessons' => $lessons, 'minutes' => self::paidMinutes($lessons), 'days' => $this->extendsBy($course, $first, $last), 'sharing' => $this->sharing($course)];
    }

    /** @return array{cancelled: int, minutes: int, days: int} */
    public function handle(string $enrollmentId, string $from, string $until, string $reason, ?string $actorId): array
    {
        $course = $this->course($enrollmentId);
        [$first, $last] = [self::day($from, 'from'), self::day($until, 'until')];

        return DB::transaction(function () use ($course, $first, $last, $reason, $actorId) {
            // The course's own rules first (running, not ending, the days); nothing else happens if they refuse.
            $this->enrollments->pause($course->id, $first, $last, $reason, $this->days->today(), $actorId);

            $days = $this->extendsBy($course, $first, $last);
            if ($days > 0 && $course->creditAccountId !== null) {
                $this->credits->extend($course->creditAccountId, $days, "pause:{$course->id}:{$first->toDateString()}:{$last->toDateString()}", $actorId, 'enrollment_paused');
            }

            $lessons = $this->lessons($course, $first, $last);
            $minutes = self::paidMinutes($lessons);
            $cancelled = 0;
            foreach ($lessons as $lesson) {
                try {
                    $this->cancel->handle($lesson, 'system', 'enrollment_paused', null, $actorId);
                    $cancelled++;
                } catch (ValidationException) {
                    continue;   // it began, or changed meanwhile: it stays as it is
                }
            }

            app(AuditTrail::class)->record('enrollment.paused', 'enrollment', $course->id, [
                'from' => $first->toDateString(), 'until' => $last->toDateString(), 'reason' => trim($reason), 'lessons' => $cancelled, 'minutes' => $minutes, 'days' => $days,
            ]);
            $this->dst->handle(enrollmentIds: [$course->id]);

            return ['cancelled' => $cancelled, 'minutes' => $minutes, 'days' => $days];
        });
    }

    /** @return Collection<int, Session> the booked lessons in the days the pause adds, from now on */
    private function lessons(EnrollmentData $course, CarbonImmutable $first, CarbonImmutable $last): Collection
    {
        // Made longer: only the days after the pause's last one so far.
        $start = $course->pausedFrom !== null && $course->pausedFrom->equalTo($first) && $course->pausedUntil !== null
            ? $course->pausedUntil->addDay() : $first;
        [$from, $until] = $this->days->span($start, $last);

        return Session::query()->with('participants')->where('enrollment_id', $course->id)->where('status', SessionStatus::Scheduled->value)
            ->where('starts_at', '>=', $from->max(CarbonImmutable::now()))->where('starts_at', '<', $until)
            ->orderBy('starts_at')->get();
    }

    /** Days the credit lasts longer: the pause's (the days added, when made longer), unless another course still draws on the balance. */
    private function extendsBy(EnrollmentData $course, CarbonImmutable $first, CarbonImmutable $last): int
    {
        $start = $course->pausedFrom !== null && $course->pausedFrom->equalTo($first) && $course->pausedUntil !== null
            ? $course->pausedUntil->addDay() : $first;
        if ($course->creditAccountId === null || $last->lessThan($start) || $this->sharing($course)) {
            return 0;
        }

        return (int) $start->diffInDays($last) + 1;
    }

    /** Another course still takes lessons from the same balance (a shared package): its validity stays as it is. */
    private function sharing(EnrollmentData $course): bool
    {
        return $course->creditAccountId !== null && DB::table('enrollments')->where('credit_account_id', $course->creditAccountId)
            ->where('id', '<>', $course->id)->whereIn('status', ['pending_payment', 'active'])->exists();
    }

    /** @param  Collection<int, Session>  $lessons */
    public static function paidMinutes(Collection $lessons): int
    {
        return (int) $lessons->filter(fn (Session $s) => in_array($s->funding_status, [FundingStatus::Funded, FundingStatus::Makeup], true))
            ->sum(fn (Session $s) => $s->durationMinutes());
    }

    private function course(string $enrollmentId): EnrollmentData
    {
        return $this->enrollments->find($enrollmentId) ?? throw ValidationException::withMessages(['enrollment' => __('scheduling::policy.errors.no_enrollment')]);
    }

    public static function day(string $value, string $field): CarbonImmutable
    {
        $date = substr($value, 0, 10);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) !== 1 || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw ValidationException::withMessages([$field => __('scheduling::courses.errors.day')]);
        }

        return CarbonImmutable::parse($date)->startOfDay();
    }
}
