<?php

namespace Modules\Credits\Contracts\Events;

/** An account just went down to its low-balance mark; Billing issues the renewal invoice. */
final readonly class CreditBalanceLow
{
    public function __construct(
        public string $academyId,
        public string $accountId,
        public string $studentId,
        public int $remainingMin,
    ) {}
}
