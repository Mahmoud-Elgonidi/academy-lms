<?php

namespace Modules\Support\Contracts\Events;

/** A family or a teacher wrote to the academy (or someone at the academy wrote it down for them). */
final readonly class TicketOpened
{
    public function __construct(public string $academyId, public string $ticketId) {}
}
