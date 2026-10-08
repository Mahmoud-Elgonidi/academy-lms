<?php

namespace Modules\Support\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Support\Contracts\Events\TicketResolved;
use Modules\Support\Enums\TicketStatus;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;
use Modules\Support\SupportRules;

/**
 * The academy says a ticket is done, usually with a last answer. A complaint
 * is decided first (confirmed or dismissed). The requester hears it and may
 * reopen it for a week; then it closes by itself.
 */
final class ResolveTicket
{
    public function __construct(
        private readonly SupportRules $rules,
        private readonly ReplyToTicket $reply,
        private readonly TenantContext $tenants,
    ) {}

    public function handle(Ticket $ticket, string $actorId, ?string $answer = null, ?UploadedFile $file = null): Ticket
    {
        if (! $this->rules->isStaff($actorId)) {
            throw new AuthorizationException;
        }
        $l = 'support::tickets.errors.';
        if (! $ticket->status->isActive()) {
            throw ValidationException::withMessages(['ticket' => __($l.'not_active')]);
        }
        if ($ticket->complaint_status === 'pending') {
            throw ValidationException::withMessages(['ticket' => __($l.'decide_first')]);
        }

        return DB::transaction(function () use ($ticket, $actorId, $answer, $file) {
            if (trim((string) $answer) !== '' || $file !== null) {
                $this->reply->handle($ticket, $actorId, $answer, $file);
            }
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $now = CarbonImmutable::now();
            $ticket->forceFill([
                'status' => TicketStatus::Resolved,
                'resolved_at' => $now,
                'first_response_at' => $ticket->first_response_at ?? $now,
            ])->save();
            TicketMessage::event($ticket, 'resolved', $actorId);
            $academyId = $this->tenants->require()->id;
            DB::afterCommit(fn () => event(new TicketResolved($academyId, $ticket->id)));

            return $ticket;
        });
    }
}
