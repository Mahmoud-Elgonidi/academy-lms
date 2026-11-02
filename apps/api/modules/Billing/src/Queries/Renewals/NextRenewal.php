<?php

namespace Modules\Billing\Queries\Renewals;

use Carbon\CarbonImmutable;
use Modules\Billing\Domain\Renewals\RenewalPace;
use Modules\Credits\Contracts\DTOs\CreditBalance;
use Modules\SharedKernel\Money\Money;

/** A course's next block, as NextRenewals reckons it: when its invoice should go out and about how much. */
final readonly class NextRenewal
{
    public function __construct(
        public string $subscriptionId,
        public RenewalPace $pace,
        public CreditBalance $balance,
        /** The day it should go out, on the academy's calendar (midnight there). */
        public CarbonImmutable $on,
        public int $days,
        /** What its invoice would come to: the block's price, its discounts and VAT. */
        public Money $amount,
    ) {}
}
