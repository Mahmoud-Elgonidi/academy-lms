<?php

namespace Modules\Crm\Support;

use Carbon\CarbonImmutable;
use Modules\Crm\Models\LeadTrial;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Http\LinkToken;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * A family's own links after a free trial, without signing in (docs/
 * architecture/02, section 27, J2), on the academy's host:
 *
 * - the trial's report, /{locale}/trial/report/{token} (LinkToken purpose
 *   "trial-report", REPORT_DAYS days), read and acted on through
 *   v1/site/trials/report/{token}… (the second trial books from it);
 * - "book again" after a trial that did not take place,
 *   /{locale}/trial/again/{token} (purpose "trial-rebook", REBOOK_DAYS days),
 *   through v1/site/trials/rebook/{token}….
 *
 * Each went to the family's own number, so it proves that number without a
 * code (as J1's "pick a time" link does).
 */
final class ReportLinks
{
    public const REPORT = 'trial-report';

    public const REBOOK = 'trial-rebook';

    public const REPORT_DAYS = 30;

    public const REBOOK_DAYS = 14;

    public function __construct(
        private readonly TenantDirectory $directory,
        private readonly TenantContext $tenants,
    ) {}

    public function report(LeadTrial $trial, string $locale): string
    {
        return $this->url($locale, 'trial/report/', LinkToken::issue(self::REPORT, $trial->id, CarbonImmutable::now()->addDays(self::REPORT_DAYS)));
    }

    public function rebook(LeadTrial $trial, string $locale): string
    {
        return $this->url($locale, 'trial/again/', LinkToken::issue(self::REBOOK, $trial->id, CarbonImmutable::now()->addDays(self::REBOOK_DAYS)));
    }

    private function url(string $locale, string $path, string $token): string
    {
        return rtrim($this->directory->baseUrl($this->tenants->require()), '/').'/'.($locale === 'en' ? 'en' : 'ar').'/'.$path.$token;
    }
}
