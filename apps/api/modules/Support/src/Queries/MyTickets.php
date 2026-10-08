<?php

namespace Modules\Support\Queries;

use Illuminate\Support\Facades\DB;
use Modules\Support\Models\Ticket;

/**
 * A family's or a teacher's own tickets, the active ones first, then the
 * latest. A guardian sees the tickets they opened (about any of their
 * children, or one of them); nobody sees another person's.
 */
final class MyTickets
{
    /** @return list<Ticket> each with student_name set */
    public function of(string $requesterId, ?string $studentId = null, int $limit = 50): array
    {
        $tickets = Ticket::query()->where('requester_id', $requesterId)
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->orderByRaw("status in ('open', 'waiting') desc")->orderByDesc('last_message_at')
            ->limit($limit)->get();
        $names = DB::table('users')->whereIn('id', $tickets->pluck('student_id')->filter()->unique()->values())->pluck('name', 'id');
        $tickets->each(fn (Ticket $t) => $t->setAttribute('student_name', $t->student_id ? ($names[$t->student_id] ?? null) : null));

        return $tickets->all();
    }

    /** How many of the requester's tickets the academy answered and now wait for them. */
    public function waitingOn(string $requesterId): int
    {
        return Ticket::query()->where('requester_id', $requesterId)->where('status', 'waiting')->count();
    }
}
