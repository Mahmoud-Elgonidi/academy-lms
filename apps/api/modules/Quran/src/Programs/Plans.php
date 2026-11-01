<?php

namespace Modules\Quran\Programs;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Quran\Domain\Programs\LessonPortion;
use Modules\Quran\Domain\Programs\MushafLayout;
use Modules\Quran\Domain\Programs\Portion;
use Modules\Quran\Models\HifzPage;
use Modules\Quran\Models\QuranPlan;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The plans and their portions (docs/architecture/02, section 26): a
 * course's live plan (or its group's, which its members share), whether it
 * is frozen (the course paused or over: nothing moves), and today's portion
 * from where the learner is (Domain\Programs\LessonPortion), its far review
 * cycling over what they have memorized (a group's: the pages every member
 * has). A Quran course without a plan has no portion.
 */
final class Plans
{
    public function __construct(
        private readonly Mushaf $mushaf,
        private readonly EnrollmentApi $enrollments,
        private readonly TenantContext $tenants,
    ) {}

    public function forEnrollment(string $enrollmentId): ?QuranPlan
    {
        $plan = QuranPlan::query()->with('program')->where('enrollment_id', $enrollmentId)->where('status', 'active')->first();
        if ($plan !== null) {
            return $plan;
        }
        // A course seated in a group shares the group's plan.
        $group = DB::table('group_members')->where('enrollment_id', $enrollmentId)->whereNull('left_on')->value('group_id');

        return $group === null ? null : $this->forGroup((string) $group);
    }

    public function forGroup(string $groupId): ?QuranPlan
    {
        return QuranPlan::query()->with('program')->where('group_id', $groupId)->where('status', 'active')->first();
    }

    /** The plan a lesson follows: its group's, or its course's. */
    public function forLesson(LessonCard $card): ?QuranPlan
    {
        return match (true) {
            $card->groupId !== null => $this->forGroup($card->groupId),
            $card->enrollmentId !== null => $this->forEnrollment($card->enrollmentId),
            default => null,
        };
    }

    /** Why nothing moves on the plan at this moment (or at a lesson that started then): paused, ended; null when it may. */
    public function frozen(QuranPlan $plan, ?CarbonImmutable $at = null): ?string
    {
        $at ??= CarbonImmutable::now();
        if ($plan->isGroup()) {
            return DB::table('lesson_groups')->where('id', $plan->group_id)->value('status') === 'ended' ? 'ended' : null;
        }
        $course = $this->enrollments->find((string) $plan->enrollment_id);

        return $course === null ? 'ended' : self::courseFrozen($course, $at, $this->timezone());
    }

    public static function courseFrozen(EnrollmentData $course, CarbonImmutable $at, string $timezone): ?string
    {
        $day = $at->setTimezone($timezone)->toDateString();
        $today = CarbonImmutable::now()->setTimezone($timezone)->toDateString();

        return match (true) {
            $course->status === EnrollmentStatus::Ended || $course->endedBy($day) || $course->endedBy($today) => 'ended',
            $course->status === EnrollmentStatus::Paused || $course->pausedOn($day) => 'paused',
            default => null,
        };
    }

    /** The plan's portion now; null without the platform's copy of the Quran (the Noorani Qaida needs none). */
    public function portion(QuranPlan $plan): ?Portion
    {
        $shape = $plan->shape();
        $layout = $this->mushaf->layout();
        if ($shape->isNoorani()) {
            return LessonPortion::make($shape, $plan->position(), $layout ?? new MushafLayout([1 => 1], [1], [1]));
        }
        if ($layout === null) {
            return null;
        }

        return LessonPortion::make($shape, $plan->position(), $layout, $this->memorized($plan), $plan->far_cursor);
    }

    /**
     * The pages the far review cycles over: the learner's memorized pages; a group's, those every member has.
     *
     * @return list<int>
     */
    public function memorized(QuranPlan $plan): array
    {
        $students = $plan->isGroup() ? $this->members((string) $plan->group_id) : [(string) $plan->student_id];
        if ($students === []) {
            return [];
        }
        $pages = HifzPage::query()->whereIn('student_id', $students)->where('state', 'memorized')
            ->groupBy('page')->havingRaw('count(distinct student_id) = ?', [count($students)])->pluck('page')->all();

        return array_map('intval', $pages);
    }

    /** @return list<string> a group's students now */
    public function members(string $groupId): array
    {
        return DB::table('group_members')->where('group_id', $groupId)->whereNull('left_on')->pluck('student_id')->map(fn ($id) => (string) $id)->all();
    }

    public function timezone(): string
    {
        return $this->tenants->current()?->defaultTimezone ?? 'UTC';
    }
}
