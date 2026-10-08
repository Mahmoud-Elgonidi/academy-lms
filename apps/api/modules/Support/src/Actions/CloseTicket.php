<?php

namespace Modules\Support\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Support\Enums\TicketStatus;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;

/**
 * Done for good: the requester closes their own ticket (solved, or no longer
 * needed), or the system closes a resolved one after a quiet week. A
 * complaint still waiting for a decision keeps waiting for it.
 */
final class CloseTicket
{
    /** @param  ?string  $byId  the requester; null for the system */
    public function handle(Ticket $ticket, ?string $byId): Ticket
    {
        if ($byId !== null && $byId !== $ticket->requester_id) {
            throw new AuthorizationException;
        }
        if ($ticket->status === TicketStatus::Closed) {
            throw ValidationException::withMessages(['ticket' => __('support::tickets.errors.closed')]);
        }

        return DB::transaction(function () use ($ticket, $byId) {
            $now = CarbonImmutable::now();
            $ticket->forceFill(['status' => TicketStatus::Closed, 'closed_at' => $now, 'resolved_at' => $ticket->resolved_at ?? $now])->save();
            TicketMessage::event($ticket, 'closed', $byId, $byId === null ? ['quiet_days' => Ticket::CLOSE_AFTER_DAYS] : [], $byId === null ? 'system' : 'requester');

            return $ticket;
        });
    }
}
