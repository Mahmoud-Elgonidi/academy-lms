<?php

namespace Modules\Crm\Support;

use Modules\Scheduling\Contracts\TrialAudience;
use Modules\Site\Contracts\SiteApi;

/** Scheduling's trial words follow the academy's published page: a page for adults (settings.audience) says a session and a trainer. */
final class TrialAudienceFromSite implements TrialAudience
{
    public function __construct(private readonly SiteApi $site) {}

    public function forAdults(): bool
    {
        return $this->site->trialSettings()->forAdults;
    }
}
