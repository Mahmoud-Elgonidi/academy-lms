<?php

namespace Modules\Support\Enums;

/**
 * Whose turn it is: open (the academy's), waiting (the requester's, after an
 * answer), resolved (the academy says it is done; the requester may reopen
 * it for a week), closed (done for good: by the requester, or a quiet week
 * after it was resolved).
 */
enum TicketStatus: string
{
    case Open = 'open';
    case Waiting = 'waiting';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return __('support::tickets.statuses.'.$this->value);
    }

    public function isActive(): bool
    {
        return $this === self::Open || $this === self::Waiting;
    }
}
