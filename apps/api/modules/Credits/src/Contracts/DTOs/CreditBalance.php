<?php

namespace Modules\Credits\Contracts\DTOs;

use Carbon\CarbonImmutable;

/**
 * A credit account at a glance, in minutes, as of now: minutes in lots past
 * their date are lost even before credits:expire sweeps them, so they are in
 * neither availableMin nor makeupMin (lapsedMin says how many), and
 * nextExpiry is always a moment still to come (the soonest lot with free
 * minutes left; null when none can lapse).
 */
final readonly class CreditBalance
{
    public function __construct(
        public string $accountId,
        public int $availableMin,
        public int $reservedMin,
        public int $consumedMin,
        public ?CarbonImmutable $nextExpiry,
        public int $expiringMin,
        public int $makeupMin = 0,
        public int $lapsedMin = 0,
    ) {}

    /** Not yet used: free to book plus already booked. */
    public function remainingMin(): int
    {
        return $this->availableMin + $this->reservedMin;
    }

    /** What renewal counts: the paid-for minutes left, makeup minutes aside, booked or not (they are owed lessons). */
    public function renewableMin(): int
    {
        return $this->remainingMin() - $this->makeupMin;
    }
}
