<?php

namespace Modules\Messaging\Queries;

use Illuminate\Support\Facades\DB;
use Modules\Messaging\Models\Conversation;

/**
 * The academy's conversations, for who oversees them: those with something
 * hidden or an outside app named since anyone last looked first, then the
 * latest; narrowed to those that need a look or are locked, or by a
 * teacher's or student's name.
 */
final class OversightBoard
{
    public const PAGE = 60;

    /**
     * @param  string  $show  all | flagged | locked
     * @return array{rows: list<array<string, mixed>>, counts: array{all: int, flagged: int, locked: int}}
     */
    public function list(string $show = 'all', string $search = ''): array
    {
        $base = fn () => Conversation::query()->where('messages_count', '>', 0);
        $needsLook = fn ($q) => $q->whereNotNull('flagged_at')->where(fn ($q) => $q->whereNull('reviewed_at')->orWhereColumn('reviewed_at', '<', 'flagged_at'));

        $query = $base();
        match ($show) {
            'flagged' => $needsLook($query),
            'locked' => $query->whereNotNull('locked_at'),
            default => null,
        };
        $search = trim($search);
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $ids = DB::table('users')->where('name', 'ilike', $like)->limit(200)->pluck('id')->all();
            $query->where(fn ($q) => $q->whereIn('teacher_id', $ids)->orWhereIn('student_id', $ids));
        }
        $rows = $query->orderByRaw('(flagged_at is not null and (reviewed_at is null or reviewed_at < flagged_at)) desc')
            ->orderByDesc('last_message_at')->limit(self::PAGE)->get();
        $names = DB::table('users')->whereIn('id', $rows->flatMap(fn (Conversation $c) => [$c->teacher_id, $c->student_id])->unique()->values()->all())->pluck('name', 'id');

        return [
            'rows' => $rows->map(fn (Conversation $c) => [
                'conversation' => $c,
                'teacher' => (string) ($names[$c->teacher_id] ?? ''),
                'student' => (string) ($names[$c->student_id] ?? ''),
            ])->all(),
            'counts' => [
                'all' => $base()->count(),
                'flagged' => $needsLook($base())->count(),
                'locked' => $base()->whereNotNull('locked_at')->count(),
            ],
        ];
    }

    public function needingLook(): int
    {
        return Conversation::query()->whereNotNull('flagged_at')->where(fn ($q) => $q->whereNull('reviewed_at')->orWhereColumn('reviewed_at', '<', 'flagged_at'))->count();
    }
}
