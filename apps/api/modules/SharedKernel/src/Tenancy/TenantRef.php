<?php

namespace Modules\SharedKernel\Tenancy;

/**
 * The academy (tenant) a request or job is acting for.
 *
 * Platform builds these from its academies table; every other module only
 * ever sees this value object, never the platform model.
 */
final readonly class TenantRef
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $nameAr,
        public string $nameEn,
        public string $defaultTimezone,
        public string $defaultLocale,
        public bool $active,
        /** How the academy writes a clock time for everyone (Time\TimeFormat: h12 or h24). */
        public string $timeFormat = 'h12',
    ) {}
}
