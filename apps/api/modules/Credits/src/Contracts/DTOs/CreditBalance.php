<?php

namespace Modules\Credits\Contracts\DTOs;

use Carbon\CarbonImmutable;

/** A credit account at a glance, in minutes. */
final readonly class CreditBalance
{
    public function __construct(
        public string $accountId,
        public int $availableMin,
        public int $reservedMin,
        public int $consumedMin,
        public ?CarbonImmutable $nextExpiry,
        public int $expiringMin,
    ) {}

    /** Not yet used: free to book plus already booked. */
    public function remainingMin(): int
    {
        return $this->availableMin + $this->reservedMin;
    }
}
