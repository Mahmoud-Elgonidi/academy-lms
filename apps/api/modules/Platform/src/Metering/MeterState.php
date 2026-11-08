<?php

namespace Modules\Platform\Metering;

use Carbon\CarbonImmutable;
use Modules\SharedKernel\Plans\Meter;

/**
 * One academy's meter now, worked out from its ledger: whether its plan
 * limits it, this month's allowance and what is left of it, the bought and
 * granted balance with its lots, the overage owed, what requests under way
 * hold, and what was used this month.
 */
final readonly class MeterState
{
    /**
     * @param  list<array{id: string, kind: string, amount: int, left: int, expires_at: CarbonImmutable, created_at: CarbonImmutable}>  $lots  the bought and granted lots still live, in spend order
     */
    public function __construct(
        public Meter $meter,
        public bool $limited,
        public int $allowance,
        public int $allowanceLeft,
        public int $bought,
        public int $debt,
        public int $held,
        public int $used,
        public array $lots,
        public string $period,
    ) {}

    /** What may still be used (overage and holds taken off); never limited without a limit. */
    public function available(): int
    {
        return $this->allowanceLeft + $this->bought - $this->debt - $this->held;
    }

    /** What of this month's allowance went (the alerts' measure). */
    public function allowanceUsed(): int
    {
        return max(0, $this->allowance - $this->allowanceLeft);
    }
}
