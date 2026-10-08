<?php

namespace Modules\Support\Contracts\Events;

/** A new message on a ticket: from the requester (the academy's turn) or the academy (the requester hears it). Internal notes are not replies. */
final readonly class TicketReplied
{
    /** @param  string  $side  requester | staff */
    public function __construct(public string $academyId, public string $ticketId, public string $messageId, public string $side) {}
}
