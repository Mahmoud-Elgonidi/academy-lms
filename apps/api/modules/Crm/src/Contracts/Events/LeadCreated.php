<?php

namespace Modules\Crm\Contracts\Events;

final readonly class LeadCreated
{
    public function __construct(public string $academyId, public string $leadId, public ?string $ownerId, public ?string $actorId) {}
}
