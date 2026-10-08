<?php

namespace Modules\Support\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Support\Enums\TicketStatus;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;
use Modules\Support\SupportRules;

/** A resolved ticket comes back: the requester within its week ("it is not solved"), or the academy. A closed one stays closed. */
final class ReopenTicket
{
    public function __construct(private readonly SupportRules $rules) {}

    public function handle(Ticket $ticket, string $byId): Ticket
    {
        $asRequester = $byId === $ticket->requester_id;
        if (! $asRequester && ! $this->rules->isStaff($byId)) {
            throw new AuthorizationException;
        }
        if ($ticket->status !== TicketStatus::Resolved || ($asRequester && ! $ticket->reopenable())) {
            throw ValidationException::withMessages(['ticket' => __('support::tickets.errors.not_reopenable')]);
        }

        return DB::transaction(function () use ($ticket, $byId, $asRequester) {
            $ticket->forceFill(['status' => TicketStatus::Open, 'resolved_at' => null])->save();
            TicketMessage::event($ticket, 'reopened', $byId, [], $asRequester ? 'requester' : 'staff');

            return $ticket;
        });
    }
}
