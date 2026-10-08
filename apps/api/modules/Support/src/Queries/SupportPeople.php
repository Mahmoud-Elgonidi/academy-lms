<?php

namespace Modules\Support\Queries;

use Illuminate\Support\Facades\DB;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Authorization\Ability;
use Modules\Support\SupportRules;

/**
 * The people the support screens choose from, read across modules (reads may
 * join): the academy's students and teachers, a learner's family (who the
 * academy can answer about them), and who at the academy answers tickets.
 */
final class SupportPeople
{
    public function __construct(private readonly SupportRules $rules, private readonly PeopleApi $people) {}

    /** @return array<string, string> id => name */
    public function withRole(string $role): array
    {
        return DB::table('users as u')
            ->join('user_roles as r', fn ($j) => $j->on('r.academy_id', '=', 'u.academy_id')->on('r.user_id', '=', 'u.id'))
            ->where('r.role', $role)->where('u.status', 'active')
            ->orderBy('u.name')->pluck('u.name', 'u.id')->map(fn ($n) => (string) $n)->all();
    }

    /** @return array<string, string> the learner and their guardians, id => name */
    public function familyOf(string $studentId): array
    {
        $ids = [$studentId, ...$this->people->guardiansOf($studentId)];

        return collect($ids)->mapWithKeys(fn (string $id) => [$id => (string) DB::table('users')->where('id', $id)->value('name')])->all();
    }

    /** @return array<string, string> who answers tickets, id => name */
    public function staff(): array
    {
        return DB::table('users')->whereIn('id', $this->rules->holders(Ability::HandleTickets))->orderBy('name')
            ->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
    }
}
