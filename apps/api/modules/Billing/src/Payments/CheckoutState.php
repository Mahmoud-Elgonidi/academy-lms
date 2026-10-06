<?php

namespace Modules\Billing\Payments;

use Modules\SharedKernel\Money\Money;

/** Where a checkout stands at the gateway. */
final readonly class CheckoutState
{
    public const OPEN = 'open';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const EXPIRED = 'expired';

    public function __construct(
        public string $status,
        public ?Money $amount = null,
        public ?string $paymentRef = null,
        public ?string $failure = null,
    ) {}
}
