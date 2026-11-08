<?php

namespace Modules\SharedKernel\Plans\Events;

use Carbon\CarbonImmutable;

/** Bought or granted balance of a meter ends in 30 days or less with some of it left (once per lot). */
final readonly class LotExpiringSoon
{
    public function __construct(
        public string $academyId,
        public string $meter,
        public string $lotId,
        public int $left,
        public CarbonImmutable $expiresAt,
    ) {}
}
