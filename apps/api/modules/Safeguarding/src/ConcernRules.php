<?php

namespace Modules\Safeguarding;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Contracts\IdentityApi;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\Safeguarding\Enums\ConcernCategory;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use Modules\SharedKernel\Authorization\Ability;

/**
 * Who may raise a concern, about what (docs/architecture/02, section 15).
 * Anyone with an area here may: a child (in the kids' look too, with a
 * child's words), a guardian about their child, a teacher about a student
 * they teach. Only the academy's safeguarding leads (safeguarding.handle)
 * read them. A lesson linked to a concern is the learner's own or one the
 * teacher taught, from the last month.
 */
final class ConcernRules
{
    public const LESSON_PAST_DAYS = 30;

    /** A teacher may raise a concern about a student they taught this recently. */
    public const STUDENT_DAYS = 120;

    /** One person raises at most this many a day (nobody floods the leads). */
    public const PER_DAY = 10;

    /** @var array<string, list<string>> */
    private array $holders = [];

    public function __construct(
        private readonly IdentityApi $identity,
        private readonly FamilyView $family,
        private readonly AttendanceApi $lessons,
    ) {}

    public function isLead(string $userId): bool
    {
        return in_array($userId, $this->leads(), true);
    }

    /** @return list<string> the academy's safeguarding leads */
    public function leads(): array
    {
        return $this->holders[Ability::HandleSafeguarding->value] ??= $this->identity->usersWithAbility(Ability::HandleSafeguarding);
    }

    public function isTeacher(string $userId): bool
    {
        return in_array($userId, $this->holders[Ability::ViewOwnPerformance->value] ??= $this->identity->usersWithAbility(Ability::ViewOwnPerformance), true);
    }

    /** The words a family reporter sees: a child's (kids), a student's about themselves (middle, teen), else the classic ones (guardians, adults). */
    public function look(string $userId): string
    {
        return match ($this->family->stage($userId)) {
            LearnerStage::Kids => 'kids',
            LearnerStage::Middle, LearnerStage::Teen => 'student',
            default => 'classic',
        };
    }

    /** @return list<ConcernCategory> */
    public function categoriesFor(string $side, string $look = 'classic'): array
    {
        return match (true) {
            $side === 'staff' => ConcernCategory::cases(),
            $side === 'teacher' => [ConcernCategory::Wellbeing, ConcernCategory::OutsideContact, ConcernCategory::AdultBehaviour, ConcernCategory::Content, ConcernCategory::Other],
            $look === 'kids' => [ConcernCategory::AdultBehaviour, ConcernCategory::OutsideContact, ConcernCategory::Bullying, ConcernCategory::Other],
            default => [ConcernCategory::AdultBehaviour, ConcernCategory::OutsideContact, ConcernCategory::Content, ConcernCategory::Bullying, ConcernCategory::Wellbeing, ConcernCategory::Other],
        };
    }

    /** The lesson, if this reporter may link it: the learner's own (family), one they taught (teacher), any (the office); from the last month. */
    public function linkableLesson(string $sessionId, string $side, ?string $studentId, string $reporterId, ?CarbonImmutable $now = null): ?LessonCard
    {
        $lesson = $this->lessons->lesson($sessionId);
        $now ??= CarbonImmutable::now();
        if ($lesson === null || $lesson->startsAt->lessThan($now->subDays(self::LESSON_PAST_DAYS)) || $lesson->startsAt->greaterThan($now->addDay())) {
            return null;
        }
        $own = match ($side) {
            'teacher' => $lesson->teacherId === $reporterId,
            'staff' => true,
            default => $studentId !== null && $lesson->studentId === $studentId,
        };

        return $own ? $lesson : null;
    }

    /**
     * Lessons a concern may be about, newest first: the learner's or the teacher's from the last month up to now.
     *
     * @return list<LessonCard>
     */
    public function lessonChoices(string $side, ?string $studentId, string $reporterId, ?CarbonImmutable $now = null, int $limit = 15): array
    {
        $now ??= CarbonImmutable::now();
        $from = $now->subDays(self::LESSON_PAST_DAYS);
        if ($side === 'teacher') {
            $cards = $this->lessons->lessonsOf($reporterId, $from, $now->addHour());
        } elseif ($studentId !== null) {
            $cards = array_values(array_filter(array_map(fn ($l) => $this->lessons->lesson($l->id), $this->lessons->lessonsOfStudent($studentId, $from, $now->addHour()))));
        } else {
            return [];
        }
        $cards = array_values(array_filter($cards, fn (LessonCard $c) => $c->startsAt->lessThanOrEqualTo($now)));
        usort($cards, fn (LessonCard $a, LessonCard $b) => $b->startsAt <=> $a->startsAt);

        return array_slice($cards, 0, $limit);
    }

    /**
     * The students a teacher taught lately (a concern may be about one of them).
     *
     * @return array<string, string> id => name
     */
    public function studentsOfTeacher(string $teacherId, ?CarbonImmutable $now = null): array
    {
        return DB::table('sessions as s')
            ->join('session_participants as p', fn ($j) => $j->on('p.academy_id', '=', 's.academy_id')->on('p.session_id', '=', 's.id')->where('p.role', '=', 'student'))
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'p.academy_id')->on('u.id', '=', 'p.user_id'))
            ->where('s.teacher_id', $teacherId)->where('s.starts_at', '>=', ($now ?? CarbonImmutable::now())->subDays(self::STUDENT_DAYS))
            ->orderBy('u.name')->distinct()->pluck('u.name', 'u.id')->all();
    }
}
