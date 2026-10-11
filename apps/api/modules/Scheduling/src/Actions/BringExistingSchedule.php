<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\NewSubscription;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Billing\Contracts\DTOs\RegionOption;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Credits\Contracts\LotSource;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\DTOs\NewEnrollment;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Contracts\DTOs\BroughtSchedule;
use Modules\Scheduling\Contracts\DTOs\ExistingSchedule;
use Modules\Scheduling\Contracts\ScheduleImport;
use Modules\Scheduling\Domain\Slots\WeeklyTime;
use Modules\Scheduling\Support\MinorsGuard;
use Modules\SharedKernel\Time\LocalTimeResolver;
use Modules\SharedKernel\Time\Timezones;

/**
 * An enrollment the academy was already running, brought over as it stands
 * (docs/architecture/01: "استيراد من CSV/Excel"): active from the start,
 * since the family already pays; its weekly times anchored on the teacher's
 * clock like any other; the lessons left of the block under way as the
 * opening balance, which funds the next lessons; and, when a plan is named,
 * a subscription whose first invoice is the next block's.
 */
final class BringExistingSchedule implements ScheduleImport
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly CreditsApi $credits,
        private readonly BillingApi $billing,
        private readonly PeopleApi $people,
        private readonly CreateRecurringSlots $slots,
        private readonly MaterializeSessions $materialize,
        private readonly FundSessions $fund,
        private readonly LocalTimeResolver $resolver,
        private readonly MinorsGuard $minors,
    ) {}

    public function exists(string $studentId, string $teacherId, string $subjectId): bool
    {
        return collect($this->enrollments->forStudent($studentId))
            ->contains(fn (EnrollmentData $e) => $e->teacherId === $teacherId && $e->subjectId === $subjectId && $e->status->keepsSlots());
    }

    public function bring(ExistingSchedule $s): BroughtSchedule
    {
        $l = 'scheduling::import.errors.';
        $role = fn (string $userId, string $role) => DB::table('user_roles')->where('user_id', $userId)->where('role', $role)->exists();
        if (! $role($s->studentId, 'student')) {
            throw ValidationException::withMessages(['student' => __($l.'student')]);
        }
        if (! $role($s->teacherId, 'teacher')) {
            throw ValidationException::withMessages(['teacher' => __($l.'teacher')]);
        }
        if (! DB::table('teacher_subjects')->where('teacher_id', $s->teacherId)->where('subject_id', $s->subjectId)->exists()) {
            throw ValidationException::withMessages(['subject' => __($l.'not_their_subject')]);
        }
        // New lessons with a child, like any other door: only with a teacher cleared for children.
        $this->minors->assert($s->teacherId, $s->studentId);
        if ($s->durationMin < 10 || $s->durationMin > 240) {
            throw ValidationException::withMessages(['duration' => __($l.'duration')]);
        }
        $count = count(array_unique(array_map(fn (array $t) => $t['weekday'].'|'.$t['time'], $s->times)));
        if ($count === 0 || $count !== count($s->times) || $count > 7) {
            throw ValidationException::withMessages(['times' => __($l.'times')]);
        }
        if ($s->remainingLessons < 0 || $s->remainingLessons > 200) {
            throw ValidationException::withMessages(['remaining' => __($l.'remaining')]);
        }
        if ($this->exists($s->studentId, $s->teacherId, $s->subjectId)) {
            throw ValidationException::withMessages(['student' => __($l.'exists')]);
        }

        $plan = null;
        if ($s->planId !== null) {
            $plan = collect($this->billing->planOptions())->first(fn (PlanOption $p) => $p->id === $s->planId);
            if ($plan === null) {
                throw ValidationException::withMessages(['plan' => __('scheduling::find.errors.plan')]);
            }
            if ($plan->sessionMinutes !== $s->durationMin || $plan->sessionsPerWeek !== $count) {
                throw ValidationException::withMessages(['plan' => __('scheduling::find.errors.plan_mismatch', ['minutes' => $s->durationMin, 'per_week' => $count])]);
            }
        }

        $teacherTz = DB::table('users')->where('id', $s->teacherId)->value('timezone') ?: 'UTC';
        $clock = $s->clock ?? $teacherTz;
        if (! Timezones::isValid($clock)) {
            throw ValidationException::withMessages(['clock' => __($l.'clock')]);
        }
        $times = $this->onTeacherClock($s, $clock, $teacherTz);

        $subject = DB::table('subjects')->where('id', $s->subjectId)->first(['name_ar', 'name_en']);

        return DB::transaction(function () use ($s, $times, $teacherTz, $plan, $subject) {
            $enrollmentId = $this->enrollments->create(new NewEnrollment($s->studentId, $s->subjectId, $s->teacherId, min(array_column($times, 'from')), $s->actorId));
            $accountId = $this->credits->openAccount($s->studentId, 'enrollment', $enrollmentId);
            $this->enrollments->attachCreditAccount($enrollmentId, $accountId);
            if ($s->remainingLessons > 0) {
                $expires = $s->creditExpiresOn?->endOfDay() ?? ($plan ? $s->startDate->addDays($plan->validityDays)->endOfDay() : null);
                $this->credits->grant($accountId, LotSource::Purchase, null, $s->remainingLessons * $s->durationMin, $expires, 'import:'.$enrollmentId, $s->actorId, 'imported');
            }
            $this->enrollments->activate($enrollmentId, $s->actorId);

            $this->slots->handle($enrollmentId, $s->teacherId, $s->studentId, $teacherTz, $times, $s->actorId);
            $result = $this->materialize->handle([$enrollmentId], actorId: $s->actorId);
            $this->fund->handle($enrollmentId);

            if ($plan !== null) {
                $this->billing->adopt(new NewSubscription(
                    enrollmentId: $enrollmentId,
                    studentId: $s->studentId,
                    creditAccountId: $accountId,
                    planId: $plan->id,
                    payerId: $s->payerId ?? ($this->people->guardiansOf($s->studentId)[0] ?? $s->studentId),
                    priceRegionId: $s->priceRegionId ?? collect($this->billing->regions())->first(fn (RegionOption $r) => $r->isDefault)?->id ?? '',
                    subjectNameAr: $subject->name_ar ?? '',
                    subjectNameEn: $subject->name_en ?? '',
                    actorId: $s->actorId,
                ));
            }

            $starts = array_map(fn ($session) => $session->starts_at, $result['created']);

            return new BroughtSchedule($enrollmentId, count($result['created']), $starts === [] ? null : min($starts), array_column($result['skipped'], 'date'));
        });
    }

    /**
     * Each time on the teacher's clock, from its first date on or after the
     * start: a time given on the student's clock lands where it falls for the
     * teacher that week.
     *
     * @return array<string, array{time: WeeklyTime, from: CarbonImmutable}>
     */
    private function onTeacherClock(ExistingSchedule $s, string $clock, string $teacherTz): array
    {
        $times = [];
        foreach ($s->times as $t) {
            if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t['time']) || $t['weekday'] < 1 || $t['weekday'] > 7) {
                throw ValidationException::withMessages(['times' => __('scheduling::import.errors.times')]);
            }
            $day = $s->startDate;
            while ($day->dayOfWeekIso !== $t['weekday']) {
                $day = $day->addDay();
            }
            $local = $this->resolver->resolve($day->toDateString(), $t['time'], $clock)->utc->setTimezone($teacherTz);
            $key = $local->dayOfWeekIso.'|'.$local->format('H:i');
            $times[$key] = ['time' => WeeklyTime::at($local->dayOfWeekIso, $local->format('H:i'), $s->durationMin), 'from' => $local->startOfDay()];
        }

        return $times;
    }
}
