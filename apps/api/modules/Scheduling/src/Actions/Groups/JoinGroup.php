<?php

namespace Modules\Scheduling\Actions\Groups;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\NewSubscription;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Enrollment\Contracts\DTOs\NewEnrollment;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\People\Contracts\AgeGroup;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\FundSessions;
use Modules\Scheduling\Actions\MaterializeSessions;
use Modules\Scheduling\Contracts\DTOs\GroupJoin;
use Modules\Scheduling\Contracts\DTOs\GroupJoined;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Groups\GroupRules;
use Modules\Scheduling\Domain\Groups\LateJoin;
use Modules\Scheduling\Domain\Models\GroupMember;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Domain\Models\SchedulingSettings;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Support\CourseDays;
use Modules\Scheduling\Support\MinorsGuard;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * A student takes a seat in a group (docs/architecture/02, "الحصص
 * الجماعية"), all or nothing: their own course with the group's teacher
 * (an enrollment, its credit account, a subscription on a group plan of the
 * group's shape and its first invoice), the membership from today, and a
 * seat in each of the group's booked lessons from now on, funded from their
 * own credit once paid. One join at a time per group (an advisory lock),
 * so two people cannot both take its last seat. Members learn together:
 * one subject and level, one age group, never minors with adults (unless
 * the academy allows mixed groups), and a teacher cleared for every minor
 * among them (MinorsGuard). A forming group starts when it has the students
 * it waits for (StartGroup). Joining a group that already began, the
 * lessons it held in its current block are made up for as the office
 * chooses (LateJoin): off the first invoice, private or group makeup
 * credit, or nothing; audited with the count and the choice.
 */
final class JoinGroup
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly CreditsApi $credits,
        private readonly BillingApi $billing,
        private readonly PeopleApi $people,
        private readonly MinorsGuard $minors,
        private readonly CourseDays $days,
        private readonly GroupSeats $seats,
        private readonly MaterializeSessions $materialize,
        private readonly FundSessions $fund,
        private readonly StartGroup $start,
    ) {}

    /** What joining would bring now: the seat it takes, the lessons missed, and the academy's choice for them. */
    public function preview(string $groupId, ?string $planId = null): array
    {
        $group = LessonGroup::query()->findOrFail($groupId);
        $today = $this->days->today()->toDateString();
        $perBlock = $planId === null ? null : collect($this->billing->planOptions(null, 'group'))->first(fn (PlanOption $p) => $p->id === $planId)?->sessionsPerBlock;
        $missed = $group->isRunning() && $perBlock !== null ? LateJoin::missed($this->held($group), $perBlock) : 0;

        return [
            'seat' => $group->seatsTaken($today) + 1,
            'capacity' => $group->capacity,
            'missed' => $missed,
            'late_join' => LateJoin::choice(null, SchedulingSettings::current()->late_join, $missed),
        ];
    }

    public function handle(string $groupId, string $studentId, GroupJoin $terms, ?string $actorId): GroupJoined
    {
        return DB::transaction(function () use ($groupId, $studentId, $terms, $actorId) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['group:'.$groupId]);
            $group = LessonGroup::query()->whereKey($groupId)->lockForUpdate()->first();
            if ($group === null || $group->status === LessonGroup::ENDED) {
                CreateGroup::refuse('group', 'ended');
            }
            $today = $this->days->today();
            $day = $today->toDateString();
            $members = $group->membersOn($day)->get();
            if ($group->members()->where('student_id', $studentId)->where(fn ($q) => $q->whereNull('left_on')->orWhere('left_on', '>', $day))->exists()) {
                CreateGroup::refuse('student', 'already');
            }
            $taken = $group->seatsTaken($day);
            CreateGroup::refuse('group', GroupRules::seat($taken, $group->capacity), ['capacity' => $group->capacity]);
            CreateGroup::refuse('group', GroupRules::capacity($group->capacity, $this->people->groupCapacity($group->teacher_id), $taken), ['max' => $this->people->groupCapacity($group->teacher_id)]);

            $plan = collect($this->billing->planOptions())->first(fn (PlanOption $p) => $p->id === $terms->planId);
            if ($plan === null) {
                CreateGroup::refuse('plan', 'plan');
            }
            CreateGroup::refuse('plan', GroupRules::plan($plan->group, $plan->sessionMinutes, $plan->sessionsPerWeek, $group->session_minutes, $group->sessions_per_week), [
                'minutes' => $group->session_minutes, 'per_week' => $group->sessions_per_week,
            ]);

            // Who learns together.
            $settings = SchedulingSettings::current();
            $age = AgeGroup::forAge($this->people->studentProfile($studentId)->age)?->value;
            $minors = $this->people->minors([$studentId, ...$members->pluck('student_id')->all()]);
            $membersMinor = $members->map(fn (GroupMember $m) => (bool) ($minors[$m->student_id] ?? false))->values()->all();
            CreateGroup::refuse('student', GroupRules::ages($group->age_group, $age, (bool) ($minors[$studentId] ?? false), $membersMinor, $settings->allow_mixed_ages), [
                'age' => $group->age_group === null ? '' : AgeGroup::from($group->age_group)->label(),
            ]);
            CreateGroup::refuse('level', GroupRules::level($group->level_id, $terms->levelId));
            $this->minors->assert($group->teacher_id, [$studentId, ...$members->pluck('student_id')->all()]);

            // The lessons missed, and what makes up for them.
            $missed = $group->isRunning() ? LateJoin::missed($this->held($group), $plan->sessionsPerBlock) : 0;
            $lessons = LateJoin::lessons($missed, $terms->lateJoinLessons);
            $choice = LateJoin::choice($terms->lateJoin, $settings->late_join, $lessons);
            if ($choice === null) {
                CreateGroup::refuse('late_join', 'late_join');
            }

            $enrollmentId = $this->enrollments->create(new NewEnrollment($studentId, $group->subject_id, $group->teacher_id, $today, $actorId));
            $accountId = $this->credits->openAccount($studentId, 'enrollment', $enrollmentId);
            $this->enrollments->attachCreditAccount($enrollmentId, $accountId);
            $subject = DB::table('subjects')->where('id', $group->subject_id)->first(['name_ar', 'name_en']);
            $invoice = $this->billing->subscribe(new NewSubscription(
                enrollmentId: $enrollmentId,
                studentId: $studentId,
                creditAccountId: $accountId,
                planId: $plan->id,
                payerId: $terms->payerId,
                priceRegionId: $terms->priceRegionId,
                subjectNameAr: $subject->name_ar ?? '',
                subjectNameEn: $subject->name_en ?? '',
                actorId: $actorId,
                couponCode: $terms->couponCode,
                lateJoinLessons: LateJoin::discountLessons($choice, $lessons),
            ));
            if (($minutes = LateJoin::makeupMinutes($choice, $lessons, $group->session_minutes)) > 0) {
                $this->credits->grantMakeup($accountId, $minutes, LateJoin::MAKEUP_DAYS, $group->id, "group:{$group->id}:enrollment:{$enrollmentId}:late_join",
                    $actorId, 'late_join', $choice === LateJoin::PRIVATE ? 'private' : 'group');
            }

            if ($group->age_group === null && $age !== null && $members->isEmpty()) {
                $group->age_group = $age;          // the ages it is for: its first member's
                $group->save();
            }
            $member = GroupMember::query()->create([
                'group_id' => $group->id, 'enrollment_id' => $enrollmentId, 'student_id' => $studentId, 'joined_on' => $day, 'created_by' => $actorId,
            ]);

            $started = false;
            if ($group->isRunning()) {
                $this->seats->seatFrom($member, CarbonImmutable::now(), null, $actorId);
                $this->materialize->groups([$group->id], actorId: $actorId);
                $this->fund->handle($enrollmentId);
            } elseif (GroupRules::startsWith($taken + 1, $group->min_to_start)) {
                $this->start->handle($group, $actorId);
                $started = true;
            }

            app(AuditTrail::class)->record('lesson_group.joined', 'lesson_group', $group->id, [
                'enrollment' => $enrollmentId, 'seat' => $taken + 1, 'capacity' => $group->capacity, 'missed' => $missed, 'late_join' => $choice, 'lessons' => $lessons,
            ]);

            return new GroupJoined(
                enrollmentId: $enrollmentId,
                memberId: $member->id,
                invoice: $invoice,
                seat: $taken + 1,
                capacity: $group->capacity,
                lessons: Session::query()->where('group_id', $group->id)->whereHas('participants', fn ($q) => $q->where('user_id', $studentId))->count(),
                missed: $missed,
                lateJoin: $choice,
                lateJoinLessons: $lessons,
                started: $started,
            );
        });
    }

    /** The group's lessons held since it started: taught, whoever came (completed, or a student absent). */
    private function held(LessonGroup $group): int
    {
        return Session::query()->where('group_id', $group->id)
            ->whereIn('status', [SessionStatus::Completed->value, SessionStatus::StudentAbsent->value])
            ->when($group->started_on !== null, fn ($q) => $q->where('starts_at', '>=', $this->days->startOf($group->started_on)))
            ->count();
    }
}
