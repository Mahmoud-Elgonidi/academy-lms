<?php

namespace Modules\Scheduling\Contracts\Events;

/** The family or the teacher disputed a decided lesson's outcome; it waits for the academy's decision. */
final readonly class LessonDisputed
{
    public function __construct(
        public string $academyId,
        public string $disputeId,
        public string $sessionId,
        public string $side,
        public string $reason,
    ) {}
}
