<?php

namespace Modules\Scheduling\Contracts\DTOs;

use Carbon\CarbonImmutable;

/** An assessment lesson to book: the course it examines, who examines, when and for how long. */
final readonly class AssessmentBooking
{
    public function __construct(
        public string $enrollmentId,
        public string $examinerId,
        public CarbonImmutable $startsAt,
        public int $durationMin = 30,
        public ?string $actorId = null,
    ) {}
}
