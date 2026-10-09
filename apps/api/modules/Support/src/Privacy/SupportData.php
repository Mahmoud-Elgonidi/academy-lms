<?php

namespace Modules\Support\Privacy;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * The tickets a person opened and what was said on them that they could
 * read (never the academy's notes), with the files they attached. Nothing
 * is erased here: a ticket's messages are never changed, and a complaint
 * is part of the academy's record about a teacher; the person's name goes
 * with their account.
 */
final class SupportData implements PersonalDataSource
{
    public function key(): string
    {
        return 'support';
    }

    public function export(string $userId): PersonalDataPart
    {
        $tickets = DB::table('tickets')->where('requester_id', $userId)->orderBy('created_at')
            ->get(['id', 'number', 'kind', 'subject', 'status', 'created_at', 'resolved_at', 'closed_at']);
        $messages = DB::table('ticket_messages')->whereIn('ticket_id', $tickets->pluck('id'))->where('kind', 'message')
            ->orderBy('created_at')->get(['ticket_id', 'author_id', 'side', 'body', 'file_path', 'file_name', 'created_at']);
        $files = [];
        foreach ($messages as $i => $m) {
            if ($m->file_path !== null && $m->author_id === $userId) {
                $files[$m->file_path] = 'support/'.($i + 1).'-'.basename((string) $m->file_name);
            }
        }

        return new PersonalDataPart([
            'tickets' => $tickets->map(fn (object $t) => array_diff_key((array) $t, ['id' => 1]) + [
                // Messages only (kind = message): the academy's notes are never the requester's to read.
                'messages' => $messages->where('ticket_id', $t->id)
                    ->map(fn (object $m) => ['from' => $m->author_id === $userId ? 'me' : 'academy', 'text' => $m->body, 'file' => $files[$m->file_path] ?? null, 'at' => $m->created_at])->values()->all(),
            ])->all(),
        ], $files);
    }

    public function blockers(string $userId): array
    {
        return [];
    }

    public function erase(string $userId): array
    {
        return [];
    }
}
