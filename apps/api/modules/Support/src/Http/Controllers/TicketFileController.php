<?php

namespace Modules\Support\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;
use Modules\Support\SupportRules;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A picture or PDF on a ticket's message, in the panels: for the requester (on the lines they see) and the academy's support. */
final class TicketFileController
{
    public function __invoke(Request $request, string $message, SupportRules $rules): StreamedResponse
    {
        $message = TicketMessage::query()->findOrFail($message);
        $ticket = Ticket::query()->findOrFail($message->ticket_id);
        $me = (string) $request->user()?->getAuthIdentifier();
        $allowed = $rules->isStaff($me) || ($ticket->requester_id === $me && $message->isPublic());
        abort_unless($allowed && $message->file_path !== null, 404);
        $inline = str_starts_with((string) $message->mime, 'image/') || $message->mime === 'application/pdf';

        return Storage::disk(TicketMessage::DISK)->response($message->file_path, $message->file_name, ['Content-Type' => (string) $message->mime], $inline ? 'inline' : 'attachment');
    }
}
