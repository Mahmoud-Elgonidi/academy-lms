<?php

namespace Modules\Platform\Domain\Metering;

/**
 * A meter's balance now, worked out from its ledger: what is left of this
 * month's allowance, of the bought and granted lots, the overage owed (taken
 * from the next allowance or lot first), and what is held for requests under
 * way. What may be used is all of it less the overage and the holds.
 */
final readonly class Balance
{
    public function __construct(
        public int $allowanceLeft,
        public int $bought,
        public int $debt,
        public int $held,
    ) {}

    public function available(): int
    {
        return $this->allowanceLeft + $this->bought - $this->debt - $this->held;
    }
}
