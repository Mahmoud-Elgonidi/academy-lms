<?php

namespace Modules\Scheduling\Contracts\DTOs;

use Carbon\CarbonImmutable;

/** What bringing a schedule over made: the enrollment, its lessons ahead, and dates the student was already busy. */
final readonly class BroughtSchedule
{
    /** @param  list<string>  $skippedDates */
    public function __construct(
        public string $enrollmentId,
        public int $sessions,
        public ?CarbonImmutable $firstSession,
        public array $skippedDates,
    ) {}
}
