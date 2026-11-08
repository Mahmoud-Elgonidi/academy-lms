<?php

namespace Modules\Platform\Domain\Metering;

use Carbon\CarbonImmutable;

/** An estimate held for a request under way, until it is settled or released. */
final readonly class Hold
{
    public function __construct(
        public string $id,
        public int $amount,
        public CarbonImmutable $createdAt,
        public bool $released = false,
    ) {}
}
