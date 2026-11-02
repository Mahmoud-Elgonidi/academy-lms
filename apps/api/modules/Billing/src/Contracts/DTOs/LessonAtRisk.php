<?php

namespace Modules\Billing\Contracts\DTOs;

use Carbon\CarbonImmutable;

/** A lesson still unpaid that rule 16 would cancel at $cancelsAt (its start, less the academy's unpaid hours). */
final readonly class LessonAtRisk
{
    public function __construct(
        public string $sessionId,
        public string $enrollmentId,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $cancelsAt,
    ) {}
}
