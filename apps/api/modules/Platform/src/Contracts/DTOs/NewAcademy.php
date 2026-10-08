<?php

namespace Modules\Platform\Contracts\DTOs;

final readonly class NewAcademy
{
    public function __construct(
        public string $slug,
        public string $nameAr,
        public string $nameEn,
        public string $baseCurrency,
        public string $defaultTimezone = 'UTC',
        public string $defaultLocale = 'ar',
        public ?string $country = null,
        public ?string $customDomain = null,
        /** The SaaS plan's code; the default plan when null. */
        public ?string $plan = null,
    ) {}
}
