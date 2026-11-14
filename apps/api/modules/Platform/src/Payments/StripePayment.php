<?php

namespace Modules\Platform\Payments;

use Modules\SharedKernel\Money\Money;

/** A Checkout Session or a PaymentIntent as Stripe has it: only PAID counts as money. */
final readonly class StripePayment
{
    public const OPEN = 'open';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const EXPIRED = 'expired';

    public function __construct(
        public string $status,
        public ?Money $amount,
        public ?string $intentId,
        public ?StripeCard $card = null,
        public ?CardDecline $decline = null,
    ) {}

    public function paid(): bool
    {
        return $this->status === self::PAID;
    }
}
