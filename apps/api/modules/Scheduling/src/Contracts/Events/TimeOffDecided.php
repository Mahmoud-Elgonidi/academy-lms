<?php

namespace Modules\Scheduling\Contracts\Events;

/** Time off was approved (or recorded by the academy), rejected or taken back; Scheduling tells the teacher. */
final readonly class TimeOffDecided
{
    public function __construct(
        public string $academyId,
        public string $timeOffId,
        public string $teacherId,
        public string $status,
        public ?string $actorId,
    ) {}
}
