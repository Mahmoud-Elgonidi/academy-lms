<?php

namespace Modules\Crm\Contracts\Events;

/** The teacher assessed a trial: level, suggested plan and notes. Sales take it from there. */
final readonly class TrialAssessed
{
    public function __construct(public string $academyId, public string $trialId, public string $leadId, public ?string $actorId) {}
}
