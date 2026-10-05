<?php

namespace Modules\Scheduling\Domain\LiveBoard;

use Carbon\CarbonImmutable;

final readonly class BoardPerson
{
    public function __construct(
        public string $id,
        public string $name,
        public string $timezone,
        public ?string $phone = null,
        public ?CarbonImmutable $joinedAt = null,
        public ?string $relationship = null,
    ) {}
}
