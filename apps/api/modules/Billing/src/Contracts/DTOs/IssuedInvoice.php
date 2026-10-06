<?php

namespace Modules\Billing\Contracts\DTOs;

use Modules\SharedKernel\Money\Money;

final readonly class IssuedInvoice
{
    public function __construct(
        public string $id,
        public string $number,
        public Money $total,
    ) {}
}
