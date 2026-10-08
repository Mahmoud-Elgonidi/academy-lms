<?php

namespace Modules\Billing\Contracts\DTOs;

use Modules\SharedKernel\Money\Money;

/**
 * What a first block would come to for a student and payer: the plan's
 * price, what comes off it (the family discount, a coupon) and what is
 * left before VAT is added, if the academy adds it. A coupon that cannot
 * be used says why (and stops the enrolment); one that waits because the
 * family discount is bigger says so (and goes on the course).
 */
final readonly class PriceQuote
{
    /** @param  list<array{label: string, amount: Money}>  $discounts */
    public function __construct(
        public Money $price,
        public array $discounts,
        public Money $total,
        public ?string $couponError = null,
        public ?string $couponNote = null,
    ) {}
}
