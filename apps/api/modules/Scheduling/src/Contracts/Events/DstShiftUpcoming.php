<?php

namespace Modules\Scheduling\Contracts\Events;

/** A clock change moves a student's hour soon (14 days: "early", 2 days: "late"); the family hears their new hour. */
final readonly class DstShiftUpcoming
{
    public function __construct(
        public string $academyId,
        public string $noticeId,
        public string $stage,
    ) {}
}
