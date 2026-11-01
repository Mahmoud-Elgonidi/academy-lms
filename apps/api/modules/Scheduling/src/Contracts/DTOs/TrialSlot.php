<?php

namespace Modules\Scheduling\Contracts\DTOs;

use Carbon\CarbonImmutable;

/** A start a family may book a free trial at, and how many teachers are free then (never who). */
final readonly class TrialSlot
{
    public function __construct(
        public CarbonImmutable $startsAt,        // UTC
        public int $teachers,
    ) {}
}
