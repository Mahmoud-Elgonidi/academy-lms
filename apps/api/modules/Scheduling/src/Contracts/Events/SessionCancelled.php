<?php

namespace Modules\Scheduling\Contracts\Events;

/** Credits (refund or makeup credit) and Communication listen to this. */
final readonly class SessionCancelled
{
    public function __construct(
        public string $academyId,
        public string $sessionId,
        public string $reason,
        public bool $makeup,
        public ?string $actorId,
    ) {}
}
