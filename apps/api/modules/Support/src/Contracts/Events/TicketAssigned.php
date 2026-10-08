<?php

namespace Modules\Support\Contracts\Events;

/** A ticket was handed to someone at the academy; they hear of it unless they took it themselves. */
final readonly class TicketAssigned
{
    public function __construct(public string $academyId, public string $ticketId, public string $assigneeId, public ?string $actorId) {}
}
