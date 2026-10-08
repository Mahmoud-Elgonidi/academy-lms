<?php

namespace Modules\Support\Contracts\Events;

/** A ticket's first answer is late (past its target, none given yet); raised once. */
final readonly class TicketOverdue
{
    public function __construct(public string $academyId, public string $ticketId) {}
}
