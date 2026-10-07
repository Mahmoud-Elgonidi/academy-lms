<?php

namespace Modules\Scheduling\Contracts\DTOs;

use Carbon\CarbonImmutable;

/**
 * A student's lessons as an academy ran them before the platform: who
 * teaches what, the weekly times (on the teacher's clock unless another one
 * is named), and what is left of the block they already paid for.
 */
final readonly class ExistingSchedule
{
    /**
     * @param  list<array{weekday: int, time: string}>  $times  ISO weekday (1 Monday … 7 Sunday) and "HH:MM", on $clock
     * @param  ?string  $clock  the IANA zone the times are on; null: the teacher's
     */
    public function __construct(
        public string $studentId,
        public string $teacherId,
        public string $subjectId,
        public int $durationMin,
        public array $times,
        public ?string $clock,
        public CarbonImmutable $startDate,
        public int $remainingLessons = 0,
        public ?CarbonImmutable $creditExpiresOn = null,
        public ?string $planId = null,
        public ?string $payerId = null,
        public ?string $priceRegionId = null,
        public ?string $actorId = null,
    ) {}
}
