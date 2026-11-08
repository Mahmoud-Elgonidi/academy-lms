<?php

namespace Modules\Platform\Domain\Metering;

use Carbon\CarbonImmutable;

/**
 * Balance an academy may draw on: this month's allowance from the plan, or
 * a lot bought or granted (purchase, bonus, a positive adjustment), with
 * what is left of it and when it ends.
 */
final readonly class Lot
{
    public function __construct(
        public string $id,
        public string $kind,
        public int $remaining,
        public CarbonImmutable $expiresAt,
        public CarbonImmutable $createdAt,
    ) {}

    public function isAllowance(): bool
    {
        return $this->kind === Ledger::ALLOWANCE;
    }

    public function liveAt(CarbonImmutable $at): bool
    {
        return $this->expiresAt->greaterThan($at);
    }
}
