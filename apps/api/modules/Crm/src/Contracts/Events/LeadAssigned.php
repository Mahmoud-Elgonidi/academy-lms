<?php

namespace Modules\Crm\Contracts\Events;

/** A lead was handed to a salesperson; they hear of it. */
final readonly class LeadAssigned
{
    public function __construct(public string $academyId, public string $leadId, public string $ownerId, public ?string $actorId) {}
}
