<?php

namespace Modules\SharedKernel\Plans\Events;

use Modules\SharedKernel\Money\Money;

/** The academy bought a pack online or automatically and it was added to its balance (once per purchase). */
final readonly class RechargeSucceeded
{
    public function __construct(
        public string $academyId,
        public string $meter,
        public int $amount,
        public Money $price,
        public string $method,
        public string $receiptNumber,
        public string $purchaseId,
    ) {}
}
