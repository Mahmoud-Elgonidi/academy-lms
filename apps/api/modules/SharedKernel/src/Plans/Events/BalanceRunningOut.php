<?php

namespace Modules\SharedKernel\Plans\Events;

/**
 * The month's allowance is used up and the bought balance, at the last 14
 * days' pace, runs out in about $days days (once per academy, meter and month).
 */
final readonly class BalanceRunningOut
{
    public function __construct(
        public string $academyId,
        public string $meter,
        public int $days,
        public int $left,
    ) {}
}
