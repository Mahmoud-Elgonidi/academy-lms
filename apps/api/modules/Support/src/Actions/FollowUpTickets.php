<?php

namespace Modules\Support\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Support\Contracts\Events\TicketOverdue;
use Modules\Support\Enums\TicketStatus;
use Modules\Support\Models\Ticket;

/**
 * Every five minutes in each academy: a ticket whose first answer is late is
 * raised once (to whoever has it, else whoever its kind concerns), and a
 * ticket resolved a quiet week ago closes.
 */
final class FollowUpTickets
{
    public function __construct(private readonly CloseTicket $close, private readonly TenantContext $tenants) {}

    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $academyId = $this->tenants->require()->id;
        $changed = 0;

        $late = Ticket::query()->whereIn('status', [TicketStatus::Open, TicketStatus::Waiting])
            ->whereNull('first_response_at')->whereNull('overdue_alerted_at')->where('respond_by', '<', $now)->get();
        foreach ($late as $ticket) {
            DB::transaction(function () use ($ticket, $now, $academyId) {
                $ticket->forceFill(['overdue_alerted_at' => $now])->save();
                DB::afterCommit(fn () => event(new TicketOverdue($academyId, $ticket->id)));
            });
            $changed++;
        }

        $quiet = Ticket::query()->where('status', TicketStatus::Resolved)
            ->where('resolved_at', '<', $now->subDays(Ticket::CLOSE_AFTER_DAYS))->get();
        foreach ($quiet as $ticket) {
            $this->close->handle($ticket, null);
            $changed++;
        }

        return $changed;
    }
}
