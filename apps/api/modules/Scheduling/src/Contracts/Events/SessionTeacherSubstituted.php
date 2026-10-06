<?php

namespace Modules\Scheduling\Contracts\Events;

/** Another teacher covers a lesson (or its own teacher takes it back); the family and both teachers hear of it. */
final readonly class SessionTeacherSubstituted
{
    public function __construct(
        public string $academyId,
        public string $sessionId,
        public string $fromTeacherId,
        public string $toTeacherId,
        public ?string $actorId,
    ) {}
}
