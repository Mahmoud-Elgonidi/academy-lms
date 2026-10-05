<?php

namespace Modules\Scheduling\Domain\LiveBoard;

use Carbon\CarbonImmutable;

/** One session, placed in a group, with what is wrong and when the system will act. */
final readonly class BoardItem
{
    /** @param  list<array{key: string, ok: bool}>  $checks  readiness of an upcoming session */
    public function __construct(
        public BoardSession $session,
        public BoardGroup $group,
        public ?BoardIssue $issue = null,
        public ?CarbonImmutable $deadline = null,
        public ?BoardEvent $handledBy = null,
        public int $minutesDone = 0,
        public array $checks = [],
    ) {}

    public function minutesLeft(CarbonImmutable $now): ?int
    {
        return $this->deadline === null ? null : max(0, (int) ceil($now->diffInSeconds($this->deadline, false) / 60));
    }

    public function progressPercent(): int
    {
        $total = $this->session->durationMin();

        return $total === 0 ? 0 : (int) min(100, round($this->minutesDone * 100 / $total));
    }
}
