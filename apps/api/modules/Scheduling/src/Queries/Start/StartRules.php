<?php

namespace Modules\Scheduling\Queries\Start;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\BillingApi;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\People\Contracts\AgeGroup;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Contracts\DTOs\StartCheck;
use Modules\Scheduling\Contracts\DTOs\TeacherSearch;
use Modules\Scheduling\Contracts\OwnCourseStart;
use Modules\Scheduling\Domain\Availability\StudentPreference;
use Modules\Scheduling\Domain\Models\CourseStart;
use Modules\Scheduling\Queries\FindTeachersQuery;
use Modules\Scheduling\Support\StartRefused;

/**
 * The rules of a family starting a course on its own (docs/architecture/02,
 * section 27, J3), asked the same way by the options, the times, the start
 * itself and the trial's report, so they never disagree:
 *
 *  - the academy lets families start (billing_settings.self_serve_enrol);
 *  - the viewer is in the classic look (a guardian, or an adult for
 *    themselves): a child's own screen never sees money;
 *  - the learner has no course in the subject that has not ended: waiting
 *    for payment (pay it or call it off first), running, ending (start again
 *    from its end), or paused (the academy resumes it);
 *  - a teacher takes them: active, teaching the subject, taking new students
 *    (or groups), the learner's age, the teacher they want (only a woman or
 *    only a man), and cleared for minors when the learner is under 18
 *    (MinorsGuard; never said why).
 */
final class StartRules implements OwnCourseStart
{
    public function __construct(
        private readonly BillingApi $billing,
        private readonly EnrollmentApi $enrollments,
        private readonly FamilyView $family,
        private readonly PeopleApi $people,
        private readonly FindTeachersQuery $find,
    ) {}

    public function check(string $viewerId, string $learnerId, string $subjectId, ?string $teacherId = null): StartCheck
    {
        try {
            $this->assertCanStart($viewerId);
            $this->assertNoCourse($learnerId, $subjectId);
        } catch (StartRefused $e) {
            return new StartCheck(false, $e->reason, $e->validator->errors()->first('start'), null, $e->extra['enrollment_id'] ?? null);
        }
        $fits = $teacherId !== null && $this->teachers($learnerId, $subjectId, [$teacherId]) !== [];

        return new StartCheck(true, null, null, $fits ? $teacherId : null);
    }

    /** The academy lets families start, and the viewer reads money (the classic look). */
    public function assertCanStart(string $viewerId): void
    {
        if ($this->family->stage($viewerId) !== LearnerStage::Classic) {
            throw StartRefused::because('family_only', status: 403);
        }
        if (! $this->billing->selfServeEnrol()) {
            throw StartRefused::because('self_serve_off', status: 403);
        }
    }

    /** No course of the learner in the subject that has not ended. */
    public function assertNoCourse(string $learnerId, string $subjectId): void
    {
        $course = $this->courseIn($learnerId, $subjectId);
        if ($course === null) {
            return;
        }
        $name = $this->firstName((string) DB::table('users')->where('id', $learnerId)->value('name'));
        $subject = $this->subjectName($subjectId);
        $extra = ['enrollment_id' => $course->id, 'status' => $course->status->value];
        $day = fn (?CarbonImmutable $d) => $d?->locale(app()->getLocale())->translatedFormat('l j F') ?? '';

        throw match (true) {
            $course->status === EnrollmentStatus::PendingPayment => StartRefused::because(
                CourseStart::query()->where('enrollment_id', $course->id)->whereNull('cancelled_at')->exists() ? 'course_pending_own' : 'course_pending',
                ['learner' => $name, 'subject' => $subject], $extra, 409),
            $course->status === EnrollmentStatus::Paused || $course->pausedFrom !== null => StartRefused::because('course_paused',
                ['learner' => $name, 'subject' => $subject, 'until' => $day($course->pausedUntil)], $extra, 409),
            $course->endsOn !== null => StartRefused::because('course_ending',
                ['learner' => $name, 'subject' => $subject, 'on' => $day($course->endsOn)], $extra, 409),
            default => StartRefused::because('has_course', ['learner' => $name, 'subject' => $subject], $extra, 409),
        };
    }

    /** The learner's course in the subject that has not ended (the newest), if any. */
    public function courseIn(string $learnerId, string $subjectId): ?EnrollmentData
    {
        foreach ($this->enrollments->forStudent($learnerId) as $course) {
            if ($course->subjectId === $subjectId && $course->status !== EnrollmentStatus::Ended) {
                return $course;
            }
        }

        return null;
    }

    /**
     * What the hard filters ask of the learner: their age group, whether they are under 18, and the teacher they want.
     *
     * @return array{age_group: ?string, minor: bool, teacher_gender: ?string, country: ?string}
     */
    public function learnerFacts(string $learnerId): array
    {
        $details = $this->people->studentDetails($learnerId);

        return [
            'age_group' => AgeGroup::forAge($this->people->studentProfile($learnerId)->age)?->value,
            'minor' => $this->people->isMinor($learnerId),
            'teacher_gender' => $details->teacherGender,
            'country' => $details->country,
        ];
    }

    /**
     * The teachers who take this learner in the subject now (Find a teacher's hard filters: the subject, taking new
     * students or groups, the learner's age, only a woman or only a man when they asked, cleared for minors), with the
     * teacher they prefer first. Keyed by id: {id, name, timezone, gender}.
     *
     * @param  list<string>|null  $only
     * @return array<string, object>
     */
    public function teachers(string $learnerId, string $subjectId, ?array $only = null, bool $forGroups = false): array
    {
        $facts = $this->learnerFacts($learnerId);
        $wants = $facts['teacher_gender'];
        [$teachers] = $this->find->eligible(new TeacherSearch(
            subjectId: $subjectId,
            preference: new StudentPreference('UTC', [], 1, 30, CarbonImmutable::now()->startOfDay(), 1, 1),
            gender: in_array($wants, ['female_only', 'male_only'], true) ? $wants : 'any',
            ageGroup: $facts['age_group'],
            forMinor: $facts['minor'],
            forGroups: $forGroups,
        ), $only);
        $list = $teachers->all();
        $prefer = ['prefer_female' => 'female', 'prefer_male' => 'male'][$wants] ?? null;
        uasort($list, fn (object $a, object $b) => [$prefer !== null && $a->gender !== $prefer, $a->name] <=> [$prefer !== null && $b->gender !== $prefer, $b->name]);

        return $list;
    }

    /** The name a family reads for a teacher: the one on their card on the academy's page, else their first name with its title ("أ. منى"). */
    public function teacherName(string $teacherId, ?string $name = null): string
    {
        $card = $this->people->publicTeacherOf($teacherId);

        return $card?->displayName ?? $this->firstName($name ?? (string) DB::table('users')->where('id', $teacherId)->value('name'));
    }

    public function firstName(string $name): string
    {
        return preg_match('/^(?:\S+\.\s+)?\S+/u', trim($name), $first) === 1 ? $first[0] : '';
    }

    public function subjectName(string $subjectId): string
    {
        $row = DB::table('subjects')->where('id', $subjectId)->first(['name_ar', 'name_en']);

        return (string) (app()->getLocale() === 'en' ? ($row->name_en ?? '') : ($row->name_ar ?? ''));
    }
}
