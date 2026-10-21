<?php

namespace Modules\Billing\Contracts\DTOs;

final readonly class RegionOption
{
    /** @param  list<string>  $countries  ISO 3166-1 alpha-2: whose families the region is for (a country is in one region at most) */
    public function __construct(
        public string $id,
        public string $name,
        public string $currency,
        public bool $isDefault,
        public array $countries = [],
    ) {}
}
