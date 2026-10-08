<?php

namespace Modules\Billing\Contracts\DTOs;

use Modules\SharedKernel\Money\Money;

/**
 * A course's money side, for its account page: what one extra lesson costs,
 * the coupon on it (and how many invoices it still takes off; null: every
 * one), the extra lessons' invoice waiting to be paid, and the block's
 * invoice waiting to be paid that a new coupon could be issued again on.
 */
final readonly class CourseBilling
{
    public function __construct(
        public string $subscriptionId,
        public Money $lessonPrice,
        public int $sessionMinutes,
        public ?string $couponCode,
        public ?string $couponValue,
        public ?int $couponTurnsLeft,
        public ?string $waitingExtraInvoiceId,
        public ?string $waitingExtraNumber,
        public ?string $waitingBlockNumber,
        public bool $active,
        public string $payerId = '',
    ) {}
}
