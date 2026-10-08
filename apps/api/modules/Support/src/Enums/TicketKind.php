<?php

namespace Modules\Support\Enums;

/** What a ticket is about. A complaint is about a teacher, and the academy confirms or dismisses it. */
enum TicketKind: string
{
    case Complaint = 'complaint';
    case Billing = 'billing';
    case Technical = 'technical';
    case Other = 'other';

    public function label(): string
    {
        return __('support::tickets.kinds.'.$this->value);
    }

    /** The priority a new ticket of this kind starts at; the academy may change it. */
    public function priority(): TicketPriority
    {
        return $this === self::Complaint ? TicketPriority::High : TicketPriority::Normal;
    }
}
