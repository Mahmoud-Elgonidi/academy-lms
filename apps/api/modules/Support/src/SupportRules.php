<?php

namespace Modules\Support;

use Carbon\CarbonImmutable;
use Modules\Identity\Contracts\IdentityApi;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use Modules\SharedKernel\Authorization\Ability;
use Modules\Support\Enums\TicketKind;

/**
 * Who may write about what (docs/architecture/02, section 12). A teacher
 * writes about a technical problem, their pay or anything else (complaints
 * about a teacher come from families). A family writes about anything, but
 * money only in the classic look (guardians and adults), and a child in the
 * kids' look does not open tickets (their guardian does). A lesson linked to
 * a ticket is the learner's own, or one the teacher teaches, from the last
 * two months or the next one.
 */
final class SupportRules
{
    public const LESSON_PAST_DAYS = 60;

    public const LESSON_AHEAD_DAYS = 30;

    /** @var array<string, list<string>> */
    private array $staff = [];

    public function __construct(
        private readonly IdentityApi $identity,
        private readonly FamilyView $family,
        private readonly AttendanceApi $lessons,
    ) {}

    /** @return list<TicketKind> what this person may open a ticket about ([] for nobody: a teacher or student account with no area here) */
    public function kindsFor(string $side, ?string $viewerId = null): array
    {
        if ($side === 'teacher') {
            return [TicketKind::Technical, TicketKind::Billing, TicketKind::Other];
        }
        $stage = $viewerId === null ? LearnerStage::Classic : $this->family->stage($viewerId);

        return match ($stage) {
            LearnerStage::Kids => [],
            LearnerStage::Classic => [TicketKind::Complaint, TicketKind::Billing, TicketKind::Technical, TicketKind::Other],
            default => [TicketKind::Complaint, TicketKind::Technical, TicketKind::Other],
        };
    }

    /** Someone at the academy who answers tickets. */
    public function isStaff(string $userId): bool
    {
        return in_array($userId, $this->holders(Ability::HandleTickets), true);
    }

    public function decidesComplaints(string $userId): bool
    {
        return in_array($userId, $this->holders(Ability::DecideComplaints), true);
    }

    public function isTeacher(string $userId): bool
    {
        return in_array($userId, $this->holders(Ability::ViewOwnPerformance), true);
    }

    /** @return list<string> */
    public function holders(Ability $ability): array
    {
        return $this->staff[$ability->value] ??= $this->identity->usersWithAbility($ability);
    }

    /** The lesson, if it may be linked: the learner's own (family) or one the teacher teaches, inside the window. */
    public function linkableLesson(string $sessionId, string $side, ?string $studentId, string $requesterId, ?CarbonImmutable $now = null): ?LessonCard
    {
        $lesson = $this->lessons->lesson($sessionId);
        $now ??= CarbonImmutable::now();
        if ($lesson === null || $lesson->startsAt->lessThan($now->subDays(self::LESSON_PAST_DAYS)) || $lesson->startsAt->greaterThan($now->addDays(self::LESSON_AHEAD_DAYS))) {
            return null;
        }
        $own = $side === 'teacher' ? $lesson->teacherId === $requesterId : $lesson->hasStudent($studentId);

        return $own ? $lesson : null;
    }

    /**
     * Lessons a new ticket may be about, newest first: the learner's (family) or the teacher's, from the last
     * two months up to today and the next week.
     *
     * @return list<LessonCard>
     */
    public function lessonChoices(string $side, ?string $studentId, string $requesterId, ?CarbonImmutable $now = null, int $limit = 20): array
    {
        $now ??= CarbonImmutable::now();
        $from = $now->subDays(self::LESSON_PAST_DAYS);
        $to = $now->addDays(7);
        if ($side === 'teacher') {
            $cards = $this->lessons->lessonsOf($requesterId, $from, $to);
        } else {
            if ($studentId === null) {
                return [];
            }
            $cards = array_values(array_filter(array_map(fn ($l) => $this->lessons->lesson($l->id), array_slice(array_reverse($this->lessons->lessonsOfStudent($studentId, $from, $to)), 0, $limit))));
        }
        usort($cards, fn (LessonCard $a, LessonCard $b) => $b->startsAt <=> $a->startsAt);

        return array_slice($cards, 0, $limit);
    }
}
