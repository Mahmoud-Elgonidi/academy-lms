<?php

namespace Modules\Crm\Contracts\Events;

/** A lead's trial lesson did not take place (they did not come, or it was not held); sales follow up. */
final readonly class TrialMissed
{
    public function __construct(public string $academyId, public string $trialId, public string $leadId, public string $outcome) {}
}
