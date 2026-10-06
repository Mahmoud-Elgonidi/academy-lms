<?php

namespace Modules\Billing\Contracts\DTOs;

final readonly class RegionOption
{
    public function __construct(
        public string $id,
        public string $name,
        public string $currency,
        public bool $isDefault,
    ) {}
}
