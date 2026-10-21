<?php

namespace Modules\Site\Contracts\Events;

/**
 * Someone asked for something from the academy's page (raised after commit):
 * a free trial lesson ("trial", which the CRM makes a lead of) or to teach
 * there ("teach"). Carries no name or number: listeners read the request
 * through SiteApi::joinRequest().
 */
final readonly class JoinRequested
{
    public function __construct(public string $academyId, public string $requestId, public string $kind) {}
}
