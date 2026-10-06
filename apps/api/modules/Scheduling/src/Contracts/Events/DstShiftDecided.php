<?php

namespace Modules\Scheduling\Contracts\Events;

/** The academy kept the teacher's or the student's time at a clock change. */
final readonly class DstShiftDecided
{
    public function __construct(
        public string $academyId,
        public string $noticeId,
        public string $decision,
        public ?string $actorId,
    ) {}
}
