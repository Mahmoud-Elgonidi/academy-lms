<?php

namespace Modules\Platform\Contracts\DTOs;

use Modules\SharedKernel\Tenancy\Audience;

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
        /** Whom it teaches: families (every academy until told otherwise) or adults only. */
        public Audience $audience = Audience::Families,
    ) {}
}
