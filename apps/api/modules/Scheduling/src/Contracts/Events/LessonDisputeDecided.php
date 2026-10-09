<?php

namespace Modules\Scheduling\Contracts\Events;

/** The academy decided a disputed lesson again: upheld (the outcome changed) or rejected (it stayed). */
final readonly class LessonDisputeDecided
{
    public function __construct(
        public string $academyId,
        public string $disputeId,
        public string $sessionId,
        public string $status,
        public string $outcomeBefore,
        public string $outcomeAfter,
    ) {}
}
