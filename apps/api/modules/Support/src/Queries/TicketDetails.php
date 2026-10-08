<?php

namespace Modules\Support\Queries;

use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;

/**
 * One ticket with what its screens show: the lesson it is about, everyone's
 * names, and its lines. The requester sees the messages and the changes that
 * are theirs to know; the academy sees everything (internal notes,
 * assignments, the complaint's decision).
 */
final class TicketDetails
{
    public function __construct(private readonly AttendanceApi $lessons) {}

    /** @return array{ticket: Ticket, lesson: ?LessonCard, names: array<string, string>, messages: list<TicketMessage>} */
    public function of(Ticket $ticket, bool $forRequester): array
    {
        $messages = $ticket->messages()->get()->filter(fn (TicketMessage $m) => ! $forRequester || $m->isPublic())->values()->all();
        $ids = array_filter([
            $ticket->requester_id, $ticket->student_id, $ticket->assignee_id, $ticket->teacher_id, $ticket->opened_by, $ticket->complaint_decided_by,
            ...array_map(fn (TicketMessage $m) => $m->author_id, $messages),
            ...array_map(fn (TicketMessage $m) => $m->data['to'] ?? null, $messages),
        ]);

        return [
            'ticket' => $ticket,
            'lesson' => $ticket->session_id ? $this->lessons->lesson($ticket->session_id) : null,
            'names' => DB::table('users')->whereIn('id', array_values(array_unique($ids)))->pluck('name', 'id')->map(fn ($n) => (string) $n)->all(),
            'messages' => $messages,
        ];
    }
}
