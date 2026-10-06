<?php

namespace Modules\Scheduling\Contracts\DTOs;

use Carbon\CarbonImmutable;
use Modules\Billing\Contracts\DTOs\IssuedInvoice;

final readonly class EnrollmentBooked
{
    /** @param  list<string>  $skippedDates  local dates the teacher or student was already booked */
    public function __construct(
        public string $enrollmentId,
        public IssuedInvoice $invoice,
        public int $sessions,
        public ?CarbonImmutable $firstSession,
        public array $skippedDates,
    ) {}
}
