<?php

namespace Modules\Support\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Support\Enums\TicketKind;
use Modules\Support\Enums\TicketPriority;
use Modules\Support\Models\SupportSettings;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;
use Modules\Support\SupportRules;

/**
 * The academy says what a ticket really is and how urgent: its kind (a
 * "technical" problem that is a complaint about the teacher, say) and its
 * priority. The targets follow the priority from when the ticket came in (the
 * first answer's only while none was given). A teacher's ticket is never a
 * complaint, and a decided complaint stays one.
 */
final class ClassifyTicket
{
    public function __construct(private readonly SupportRules $rules) {}

    public function handle(Ticket $ticket, TicketKind $kind, TicketPriority $priority, string $actorId): Ticket
    {
        if (! $this->rules->isStaff($actorId)) {
            throw new AuthorizationException;
        }
        $l = 'support::tickets.errors.';
        if ($kind === TicketKind::Complaint && $ticket->side === 'teacher') {
            throw ValidationException::withMessages(['kind' => __($l.'teacher_complaint')]);
        }
        if ($ticket->isComplaint() && $kind !== TicketKind::Complaint && $ticket->complaint_status !== 'pending') {
            throw ValidationException::withMessages(['kind' => __($l.'decided')]);
        }
        if ($ticket->kind === $kind && $ticket->priority === $priority) {
            return $ticket;
        }

        return DB::transaction(function () use ($ticket, $kind, $priority, $actorId) {
            $was = ['kind' => $ticket->kind->value, 'priority' => $ticket->priority->value];
            $targets = SupportSettings::targetsFor($priority);
            $changes = ['kind' => $kind, 'priority' => $priority];
            if ($ticket->kind !== $kind) {
                $changes['complaint_status'] = $kind === TicketKind::Complaint ? 'pending' : null;
                if ($kind === TicketKind::Complaint && $ticket->teacher_id === null && $ticket->session_id !== null) {
                    $changes['teacher_id'] = DB::table('sessions')->where('id', $ticket->session_id)->value('teacher_id');
                }
            }
            if ($ticket->priority !== $priority) {
                $changes['resolve_by'] = $ticket->created_at->addHours($targets['resolve']);
                if ($ticket->first_response_at === null) {
                    $changes['respond_by'] = $ticket->created_at->addHours($targets['respond']);
                    $changes['overdue_alerted_at'] = null;
                }
            }
            $ticket->forceFill($changes)->save();
            if ($was['kind'] !== $kind->value) {
                TicketMessage::event($ticket, 'kind', $actorId, ['from' => $was['kind'], 'to' => $kind->value]);
            }
            if ($was['priority'] !== $priority->value) {
                TicketMessage::event($ticket, 'priority', $actorId, ['from' => $was['priority'], 'to' => $priority->value]);
            }

            return $ticket;
        });
    }
}
