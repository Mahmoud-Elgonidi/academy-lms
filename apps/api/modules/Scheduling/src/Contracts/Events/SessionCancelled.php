<?php

namespace Modules\Scheduling\Contracts\Events;

/** A session was cancelled; Scheduling tells the family and the teacher through Communication. */
final readonly class SessionCancelled
{
    public function __construct(
        public string $academyId,
        public string $sessionId,
        public string $reason,
        public bool $makeup,
        public ?string $actorId,
        public ?string $askedBy = null,     // student | teacher | academy | family | system (CancelSession), null otherwise
    ) {}
}
