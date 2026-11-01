<?php

namespace Modules\Crm\Contracts\Events;

/** The family cancelled a lead's free trial (raised after commit): whoever follows the lead hears of it. */
final readonly class TrialCancelledByFamily
{
    public function __construct(public string $academyId, public string $trialId, public string $leadId) {}
}
