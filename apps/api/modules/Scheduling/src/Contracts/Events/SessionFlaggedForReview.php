<?php

namespace Modules\Scheduling\Contracts\Events;

final readonly class SessionFlaggedForReview
{
    public function __construct(
        public string $academyId,
        public string $sessionId,
        public string $reason,
    ) {}
}
