<?php

namespace Modules\Platform\Payments;

/** A card Stripe kept for later charges: its references (never shown) and what is shown. */
final readonly class StripeCard
{
    public function __construct(
        public string $customer,
        public string $paymentMethod,
        public string $brand,
        public string $last4,
        public int $expMonth,
        public int $expYear,
    ) {}
}
