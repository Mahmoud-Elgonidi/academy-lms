<?php

namespace Modules\Support\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Support\Enums\TicketPriority;

/**
 * The academy's tickets as the support screen lists them, by tab: the ones
 * waiting for an answer (late first answers first, then by target), the
 * viewer's own, nobody's, the ones waiting for the family or teacher,
 * complaints to decide, and the finished ones. With the names of everyone
 * involved, read across modules (reads may join).
 */
final class TicketQueue
{
    public const TABS = ['todo', 'mine', 'unassigned', 'waiting', 'complaints', 'done'];

    public const PAGE = 50;

    /** @return array<string, int> tab => how many */
    public function counts(string $viewerId): array
    {
        $row = DB::table('tickets')->selectRaw("
            count(*) filter (where status = 'open') as todo,
            count(*) filter (where status in ('open', 'waiting') and assignee_id = ?) as mine,
            count(*) filter (where status in ('open', 'waiting') and assignee_id is null) as unassigned,
            count(*) filter (where status = 'waiting') as waiting,
            count(*) filter (where complaint_status = 'pending') as complaints,
            count(*) filter (where status = 'open' and first_response_at is null and respond_by < now()) as late", [$viewerId])->first();

        return array_map('intval', (array) $row) + ['done' => 0];
    }

    /**
     * @param  array{kind?: ?string, priority?: ?string, q?: ?string}  $filters
     * @return list<object> rows: the ticket's columns plus requester, student, assignee and teacher names
     */
    public function list(string $tab, string $viewerId, array $filters = [], int $limit = self::PAGE): array
    {
        $q = $this->base();
        match ($tab) {
            'mine' => $q->whereIn('t.status', ['open', 'waiting'])->where('t.assignee_id', $viewerId),
            'unassigned' => $q->whereIn('t.status', ['open', 'waiting'])->whereNull('t.assignee_id'),
            'waiting' => $q->where('t.status', 'waiting'),
            'complaints' => $q->where('t.complaint_status', 'pending'),
            'done' => $q->whereIn('t.status', ['resolved', 'closed']),
            default => $q->where('t.status', 'open'),
        };
        if (! empty($filters['kind'])) {
            $q->where('t.kind', $filters['kind']);
        }
        if (! empty($filters['priority'])) {
            $q->where('t.priority', $filters['priority']);
        }
        if (($search = trim((string) ($filters['q'] ?? ''))) !== '') {
            $number = ltrim($search, '#');
            $q->where(function (Builder $w) use ($search, $number) {
                $w->where('t.subject', 'ilike', '%'.addcslashes($search, '%_\\').'%')
                    ->orWhere('r.name', 'ilike', '%'.addcslashes($search, '%_\\').'%')
                    ->orWhere('st.name', 'ilike', '%'.addcslashes($search, '%_\\').'%');
                if (ctype_digit($number)) {
                    $w->orWhere('t.number', (int) $number);
                }
            });
        }
        if (in_array($tab, ['todo', 'mine', 'unassigned'], true)) {
            // The academy's turn: a late first answer first, then the nearest target, the more urgent first.
            $ranks = collect(TicketPriority::cases())->map(fn (TicketPriority $p) => "when '{$p->value}' then {$p->rank()}")->implode(' ');
            $q->orderByRaw('(t.first_response_at is null and t.respond_by < now()) desc')
                ->orderByRaw('case when t.first_response_at is null then t.respond_by else t.resolve_by end')
                ->orderByRaw("case t.priority {$ranks} end");
        } else {
            $q->orderByDesc('t.last_message_at');
        }

        return $q->limit($limit)->get()->all();
    }

    /** @return list<object> a teacher's complaints, newest first (the teacher's page in the back office) */
    public function complaintsAbout(string $teacherId, ?CarbonImmutable $since = null): array
    {
        return $this->base()->where('t.kind', 'complaint')->where('t.teacher_id', $teacherId)
            ->when($since, fn ($q) => $q->where('t.created_at', '>=', $since))
            ->orderByDesc('t.created_at')->get()->all();
    }

    private function base(): Builder
    {
        $user = fn (string $alias, string $column) => fn ($j) => $j->on("{$alias}.academy_id", '=', 't.academy_id')->on("{$alias}.id", '=', "t.{$column}");

        return DB::table('tickets as t')
            ->join('users as r', $user('r', 'requester_id'))
            ->leftJoin('users as st', $user('st', 'student_id'))
            ->leftJoin('users as a', $user('a', 'assignee_id'))
            ->leftJoin('users as te', $user('te', 'teacher_id'))
            ->select('t.*', 'r.name as requester_name', 'st.name as student_name', 'a.name as assignee_name', 'te.name as teacher_name');
    }
}
