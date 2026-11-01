<?php

namespace Modules\Billing\Contracts\DTOs;

use Carbon\CarbonImmutable;

/** A personal coupon as it stands now: whether it may still be used, has been, or ended. */
final readonly class PersonalCouponState
{
    public function __construct(
        public string $id,
        public string $code,
        public string $payerId,
        public int $percentBp,
        public CarbonImmutable $expiresAt,
        public bool $used,                  // on a course (or it took something off an invoice)
        public bool $active,                // not turned off (an erased payer's is)
    ) {}

    /** Usable now: on, unused and before its moment. */
    public function open(?CarbonImmutable $now = null): bool
    {
        return $this->active && ! $this->used && $this->expiresAt->isAfter($now ?? CarbonImmutable::now());
    }
}
