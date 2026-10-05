<?php

namespace Modules\Scheduling\Domain\LiveBoard;

use Carbon\CarbonImmutable;
use Modules\Scheduling\Domain\AttendancePolicy;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;

/**
 * Sorts the sessions around now into what needs someone, what runs fine,
 * what starts within the hour, and what has finished. Pure: same input,
 * same board, so it is tested without a database.
 */
final class BoardClassifier
{
    public const UPCOMING_WINDOW_MIN = 60;

    public function __construct(private readonly AttendancePolicy $policy) {}

    /**
     * @param  list<BoardSession>  $sessions
     * @return array{attention: list<BoardItem>, fine: list<BoardItem>, upcoming: list<BoardItem>, done: list<BoardItem>}
     */
    public function classify(array $sessions, CarbonImmutable $now): array
    {
        $groups = ['attention' => [], 'fine' => [], 'upcoming' => [], 'done' => []];

        foreach ($sessions as $session) {
            $item = $this->place($session, $sessions, $now);
            if ($item !== null) {
                $groups[$item->group->value][] = $item;
            }
        }

        usort($groups['attention'], fn (BoardItem $a, BoardItem $b) => [$b->issue->severity(), $a->deadline?->getTimestamp() ?? PHP_INT_MAX, $a->session->startsAt->getTimestamp()]
            <=> [$a->issue->severity(), $b->deadline?->getTimestamp() ?? PHP_INT_MAX, $b->session->startsAt->getTimestamp()]);
        usort($groups['fine'], fn (BoardItem $a, BoardItem $b) => $a->session->endsAt <=> $b->session->endsAt);
        usort($groups['upcoming'], fn (BoardItem $a, BoardItem $b) => $a->session->startsAt <=> $b->session->startsAt);
        usort($groups['done'], fn (BoardItem $a, BoardItem $b) => $b->session->endsAt <=> $a->session->endsAt);

        return $groups;
    }

    /** @param  list<BoardSession>  $all */
    private function place(BoardSession $s, array $all, CarbonImmutable $now): ?BoardItem
    {
        if ($s->status === SessionStatus::NeedsReview) {
            return new BoardItem($s, BoardGroup::Attention, BoardIssue::NeedsReview, handledBy: $s->lastHandling($s->endsAt));
        }

        if ($s->status->isFinal()) {
            return new BoardItem($s, BoardGroup::Done, minutesDone: $this->minutesDone($s, $s->endsAt));
        }

        if ($s->startsAt->greaterThan($now)) {
            if ($s->startsAt->greaterThan($now->addMinutes(self::UPCOMING_WINDOW_MIN))) {
                return null;
            }

            if ($s->funding === FundingStatus::Unfunded && ! $s->hasDecision('allow_unfunded')) {
                return new BoardItem($s, BoardGroup::Attention, BoardIssue::Unfunded, deadline: $s->startsAt);
            }

            return new BoardItem($s, BoardGroup::Upcoming, checks: $this->readiness($s, $all));
        }

        $teacherIn = $s->teacher->joinedAt !== null;
        $studentIn = $s->student?->joinedAt !== null;

        if (! $teacherIn && $now->greaterThanOrEqualTo($s->startsAt->addMinutes($this->policy->teacherLateAfterMin))) {
            $since = $s->startsAt->addMinutes($this->policy->teacherLateAfterMin);

            return new BoardItem(
                $s, BoardGroup::Attention,
                $studentIn ? BoardIssue::TeacherNotJoined : BoardIssue::NobodyJoined,
                deadline: $s->startsAt->addMinutes($this->policy->teacherAbsentAfterMin),
                handledBy: $s->lastHandling($since),
            );
        }

        if ($teacherIn && ! $studentIn && $now->greaterThanOrEqualTo($s->startsAt->addMinutes($this->policy->studentNudgeAfterMin))) {
            return new BoardItem(
                $s, BoardGroup::Attention, BoardIssue::StudentNotJoined,
                deadline: $s->startsAt->addMinutes($this->policy->studentWaitMin($s->durationMin())),
                handledBy: $s->lastHandling($s->startsAt->addMinutes($this->policy->studentNudgeAfterMin)),
            );
        }

        $windowStart = $now->subMinutes($this->policy->weakConnectionWindowMin);
        $recentDrops = array_filter($s->eventsOf(SessionEventKind::ConnectionDrop), fn (BoardEvent $e) => $e->at->greaterThanOrEqualTo($windowStart));
        if (count($recentDrops) >= $this->policy->weakConnectionDrops) {
            return new BoardItem(
                $s, BoardGroup::Attention, BoardIssue::WeakConnection,
                handledBy: $s->lastHandling($windowStart),
                minutesDone: $this->minutesDone($s, $now),
            );
        }

        return new BoardItem($s, BoardGroup::Fine, minutesDone: $this->minutesDone($s, $now));
    }

    /** Minutes taught so far: from when both were in, to now (or the end). */
    private function minutesDone(BoardSession $s, CarbonImmutable $until): int
    {
        if ($s->teacher->joinedAt === null || $s->student?->joinedAt === null) {
            return 0;
        }

        $from = $s->teacher->joinedAt->max($s->student->joinedAt)->max($s->startsAt);
        $to = $until->min($s->endsAt);

        return max(0, (int) floor($from->diffInSeconds($to, false) / 60));
    }

    /**
     * @param  list<BoardSession>  $all
     * @return list<array{key: string, ok: bool}>
     */
    private function readiness(BoardSession $s, array $all): array
    {
        $teacherBusy = false;
        foreach ($all as $other) {
            if ($other->id !== $s->id && $other->teacher->id === $s->teacher->id && $other->status->isOpen()
                && $other->startsAt->lessThan($s->startsAt) && $other->endsAt->greaterThan($s->startsAt)) {
                $teacherBusy = true;
            }
        }

        return [
            ['key' => $teacherBusy ? 'teacher_busy' : 'teacher_free', 'ok' => ! $teacherBusy],
            match ($s->funding) {
                FundingStatus::Unfunded => ['key' => 'unfunded_allowed', 'ok' => false],
                FundingStatus::Free => ['key' => 'free', 'ok' => true],
                FundingStatus::Makeup => ['key' => 'makeup_credit', 'ok' => true],
                FundingStatus::Funded => ['key' => 'funded', 'ok' => true],
            },
        ];
    }
}
