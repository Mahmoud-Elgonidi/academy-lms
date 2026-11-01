<?php

namespace Modules\Billing\Contracts\DTOs;

use Carbon\CarbonImmutable;
use Modules\Billing\Contracts\CouponPurpose;

/**
 * A personal coupon to make for one payer: a percentage off the first
 * invoice of one course, usable once, by them alone, until a moment. The
 * code is made by Billing and never needs typing (whoever starts from the
 * offer has it applied for them).
 */
final readonly class PersonalCoupon
{
    public function __construct(
        public CouponPurpose $purpose,
        public string $payerId,
        public int $percentBp,               // 1000 = 10%
        public CarbonImmutable $expiresAt,   // usable until this moment
        public string $name,                 // for staff: what it was made for
        public ?string $actorId = null,
    ) {}
}
