<?php

namespace Modules\Scheduling\Contracts\DTOs;

/** The commercial side of an enrolment, chosen with the weekly times. */
final readonly class EnrollmentTerms
{
    public function __construct(
        public string $planId,
        public string $payerId,
        public string $priceRegionId,
    ) {}
}
