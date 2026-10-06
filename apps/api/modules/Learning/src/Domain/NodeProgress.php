<?php

namespace Modules\Learning\Domain;

use Carbon\CarbonImmutable;

/** Where one student stands on one lesson, rebuilt from the reports in the order they were taught. */
final readonly class NodeProgress
{
    public const STATUSES = ['in_progress', 'mastered', 'needs_review'];

    public function __construct(
        public string $nodeId,
        public string $status = 'in_progress',
        public int $timesSeen = 0,
        public int $highScores = 0,
        public ?int $lastScore = null,
        public ?string $lastOutcome = null,
        public ?CarbonImmutable $firstSeenAt = null,
        public ?CarbonImmutable $masteredAt = null,
        public ?int $reviewIntervalDays = null,
        public ?CarbonImmutable $nextReviewAt = null,
    ) {}

    /** Mastered once stays counted for the progress %, even while it is due for review. */
    public function isMastered(): bool
    {
        return $this->masteredAt !== null;
    }

    public function isReviewDue(CarbonImmutable $at): bool
    {
        return $this->nextReviewAt !== null && $this->nextReviewAt->lessThanOrEqualTo($at);
    }
}
