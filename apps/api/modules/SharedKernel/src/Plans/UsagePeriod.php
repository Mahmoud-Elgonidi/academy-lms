<?php

namespace Modules\SharedKernel\Plans;

use Carbon\CarbonImmutable;

/** The calendar month a monthly count covers, on the academy's clock, as UTC instants [from, to). */
final readonly class UsagePeriod
{
    public function __construct(public CarbonImmutable $from, public CarbonImmutable $to) {}

    public static function monthOf(CarbonImmutable $at, string $timezone): self
    {
        $local = $at->setTimezone($timezone)->startOfMonth();

        return new self($local->utc(), $local->addMonth()->utc());
    }

    /** The month's first day, as the academy writes it (the key a count is kept under). */
    public function key(string $timezone): string
    {
        return $this->from->setTimezone($timezone)->toDateString();
    }
}
