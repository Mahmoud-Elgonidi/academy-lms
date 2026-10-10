<?php

namespace Modules\Messaging\Privacy;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * A person's conversations: those they are in as the teacher or the
 * student, every message as it was read, and what they wrote elsewhere (a
 * guardian's words in a child's conversation), with the words as typed
 * when something was hidden from their own. Nothing is erased here: the
 * conversations are the academy's record of what was said to a child; the
 * person's name goes with their account.
 */
final class MessagingData implements PersonalDataSource
{
    public function key(): string
    {
        return 'messaging';
    }

    public function export(string $userId): PersonalDataPart
    {
        $talks = DB::table('conversations')->where('teacher_id', $userId)->orWhere('student_id', $userId)->orderBy('created_at')
            ->get(['id', 'teacher_id', 'student_id', 'created_at', 'locked_at']);
        $written = DB::table('conversation_messages')->where('author_id', $userId)->distinct()->pluck('conversation_id');
        $others = DB::table('conversations')->whereIn('id', $written)->whereNotIn('id', $talks->pluck('id'))->get(['id', 'teacher_id', 'student_id', 'created_at', 'locked_at']);
        $all = $talks->concat($others);
        $names = DB::table('users')->whereIn('id', $all->flatMap(fn (object $c) => [$c->teacher_id, $c->student_id])->unique()->values())->pluck('name', 'id');
        $messages = DB::table('conversation_messages')->whereIn('conversation_id', $all->pluck('id'))->orderBy('created_at')
            ->get(['conversation_id', 'author_id', 'side', 'body', 'hidden', 'original', 'created_at']);

        return new PersonalDataPart([
            'conversations' => $all->map(fn (object $c) => [
                'teacher' => $names[$c->teacher_id] ?? null,
                'student' => $names[$c->student_id] ?? null,
                'started_at' => $c->created_at,
                'paused_at' => $c->locked_at,
                // A guardian's copy holds their own words in a child's conversation; the rest is the child's.
                'messages' => $messages->where('conversation_id', $c->id)
                    ->filter(fn (object $m) => $talks->contains('id', $c->id) || $m->author_id === $userId)
                    ->map(fn (object $m) => [
                        'from' => $m->author_id === $userId ? 'me' : $m->side,
                        'text' => $m->body,
                        'hidden' => json_decode((string) $m->hidden, true) ?: [],
                        'as_typed' => $m->author_id === $userId && $m->original !== null ? decrypt($m->original, false) : null,
                        'at' => $m->created_at,
                    ])->values()->all(),
            ])->values()->all(),
        ]);
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
