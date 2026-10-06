<?php

namespace Modules\Crm\Contracts\Events;

final readonly class LeadLost
{
    public function __construct(public string $academyId, public string $leadId, public string $reason, public ?string $actorId) {}
}
