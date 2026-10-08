<?php

namespace Modules\Support\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Support\Attachments;
use Modules\Support\Contracts\Events\TicketReplied;
use Modules\Support\Enums\TicketStatus;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;
use Modules\Support\SupportRules;
use Throwable;

/**
 * A message on a ticket. From the requester it is the academy's turn again
 * (a resolved ticket reopens, within its week); from the academy it is the
 * requester's turn, and the first one meets the first-answer target. An
 * internal note is the academy's alone: the requester never sees it and
 * nobody's turn changes. A closed ticket takes no more messages.
 */
final class ReplyToTicket
{
    public function __construct(private readonly SupportRules $rules, private readonly TenantContext $tenants) {}

    public function handle(Ticket $ticket, string $authorId, ?string $body, ?UploadedFile $file = null, bool $note = false): TicketMessage
    {
        $asRequester = $authorId === $ticket->requester_id;
        if (! $asRequester && ! $this->rules->isStaff($authorId)) {
            throw new AuthorizationException;
        }
        if ($note && $asRequester) {
            throw new AuthorizationException;
        }
        $l = 'support::tickets.';
        if ($ticket->status === TicketStatus::Closed) {
            throw ValidationException::withMessages(['ticket' => __($l.'errors.closed')]);
        }
        if ($asRequester && $ticket->status === TicketStatus::Resolved && ! $ticket->reopenable()) {
            throw ValidationException::withMessages(['ticket' => __($l.'errors.closed')]);
        }
        $body = trim((string) $body);
        Validator::make(['body' => $body, 'file' => $file], [
            'body' => [$file === null ? 'required' : 'nullable', 'string', 'max:5000'],
            'file' => Attachments::rule(),
        ], [], trans($l.'attributes'))->validate();

        $academyId = $this->tenants->require()->id;
        $stored = Attachments::store($file, $academyId, $ticket->id);
        try {
            return DB::transaction(function () use ($ticket, $authorId, $body, $note, $asRequester, $stored, $academyId) {
                $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
                $now = CarbonImmutable::now();
                $message = TicketMessage::query()->create([
                    'ticket_id' => $ticket->id,
                    'author_id' => $authorId,
                    'side' => $asRequester ? 'requester' : 'staff',
                    'kind' => $note ? 'note' : 'message',
                    'body' => $body === '' ? null : $body,
                ] + $stored);
                if ($note) {
                    $ticket->touch();

                    return $message;
                }
                $reopened = $ticket->status === TicketStatus::Resolved;
                $ticket->forceFill([
                    'status' => $asRequester ? TicketStatus::Open : TicketStatus::Waiting,
                    'resolved_at' => null,
                    'last_message_at' => $now,
                    'last_side' => $asRequester ? 'requester' : 'staff',
                    'first_response_at' => $ticket->first_response_at ?? ($asRequester ? null : $now),
                ])->save();
                if ($reopened) {
                    TicketMessage::event($ticket, 'reopened', $authorId, ['by_reply' => true], $asRequester ? 'requester' : 'staff');
                }
                DB::afterCommit(fn () => event(new TicketReplied($academyId, $ticket->id, $message->id, $asRequester ? 'requester' : 'staff')));

                return $message;
            });
        } catch (Throwable $e) {
            Attachments::forget($stored);
            throw $e;
        }
    }
}
