<?php

namespace Modules\Scheduling\Contracts\Events;

/** A teacher asked for time off; Scheduling tells whoever decides leave. */
final readonly class TimeOffRequested
{
    public function __construct(
        public string $academyId,
        public string $timeOffId,
        public string $teacherId,
        public ?string $actorId,
    ) {}
}
