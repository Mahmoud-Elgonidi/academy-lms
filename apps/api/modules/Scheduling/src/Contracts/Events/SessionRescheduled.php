<?php

namespace Modules\Scheduling\Contracts\Events;

/** A session moved to another time with the same teacher; Scheduling tells the family and the teacher through Communication. */
final readonly class SessionRescheduled
{
    public function __construct(
        public string $academyId,
        public string $sessionId,
        public string $fromUtc,
        public ?string $actorId,
    ) {}
}
