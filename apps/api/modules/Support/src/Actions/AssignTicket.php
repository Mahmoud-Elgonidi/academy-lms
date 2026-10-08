<?php

namespace Modules\Support\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Support\Contracts\Events\TicketAssigned;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;
use Modules\Support\SupportRules;

/** Hands a ticket to someone at the academy who answers tickets (or to nobody); they hear of it unless they took it themselves. */
final class AssignTicket
{
    public function __construct(private readonly SupportRules $rules, private readonly TenantContext $tenants) {}

    public function handle(Ticket $ticket, ?string $assigneeId, string $actorId): Ticket
    {
        if (! $this->rules->isStaff($actorId)) {
            throw new AuthorizationException;
        }
        if ($assigneeId !== null && ! $this->rules->isStaff($assigneeId)) {
            throw ValidationException::withMessages(['assignee_id' => __('support::tickets.errors.assignee')]);
        }
        if ($ticket->assignee_id === $assigneeId) {
            return $ticket;
        }

        return DB::transaction(function () use ($ticket, $assigneeId, $actorId) {
            $from = $ticket->assignee_id;
            $ticket->forceFill(['assignee_id' => $assigneeId])->save();
            TicketMessage::event($ticket, 'assigned', $actorId, ['from' => $from, 'to' => $assigneeId]);
            $academyId = $this->tenants->require()->id;
            if ($assigneeId !== null && $assigneeId !== $actorId) {
                DB::afterCommit(fn () => event(new TicketAssigned($academyId, $ticket->id, $assigneeId, $actorId)));
            }

            return $ticket;
        });
    }
}
