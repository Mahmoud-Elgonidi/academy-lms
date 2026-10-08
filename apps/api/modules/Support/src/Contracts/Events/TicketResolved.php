<?php

namespace Modules\Support\Contracts\Events;

/** The academy says a ticket is done; the requester hears it and may reopen it for a week. */
final readonly class TicketResolved
{
    public function __construct(public string $academyId, public string $ticketId) {}
}
