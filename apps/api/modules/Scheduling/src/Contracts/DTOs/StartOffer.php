<?php

namespace Modules\Scheduling\Contracts\DTOs;

/** A trial's offer for the course a family starts on its own: the personal coupon Billing keeps (its code, rate and end come from Billing). */
final readonly class StartOffer
{
    public function __construct(
        public string $couponId,
        public string $trialSessionId,       // the trial it followed
    ) {}
}
