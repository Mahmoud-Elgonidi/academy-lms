<?php

namespace Modules\Platform\Metering;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Domain\Metering\Ledger;
use Modules\SharedKernel\Plans\Meter;
use Modules\SharedKernel\Plans\UsagePeriod;

/**
 * What the academy's usage page (and the console's academy page) reads from
 * the usage ledger, each time: the use of each day of a month on the
 * academy's clock, the month's use by helper and by person (where known),
 * and the ledger as a statement with the balance after each line. Names come
 * from the academy's own accounts; nothing else about a person.
 */
final class UsageReport
{
    public function __construct(private readonly Meters $meters) {}

    /** @return array<int, int> day of the month => use, for every day of it */
    public function daily(string $academyId, Meter $meter, UsagePeriod $month): array
    {
        $tz = $this->meters->zone($academyId);
        $rows = $this->uses($academyId, $meter, $month)
            ->groupByRaw('1')->selectRaw('extract(day from (occurred_at at time zone ?))::int as day, -sum(amount) as used', [$tz])
            ->pluck('used', 'day');
        $days = (int) $month->from->setTimezone($tz)->daysInMonth;
        $out = [];
        for ($d = 1; $d <= $days; $d++) {
            $out[$d] = (int) ($rows[$d] ?? 0);
        }

        return $out;
    }

    /** @return array<string, int> what used it (an AI capability's key, "lesson") => use, most first */
    public function bySubject(string $academyId, Meter $meter, UsagePeriod $month): array
    {
        return $this->uses($academyId, $meter, $month)->groupBy('subject')
            ->selectRaw("coalesce(subject, '') as subject, -sum(amount) as used")->orderByDesc('used')
            ->pluck('used', 'subject')->map(fn ($n) => (int) $n)->all();
    }

    /** @return list<array{name: ?string, used: int}> by person where known (none: null), most first */
    public function byPerson(string $academyId, Meter $meter, UsagePeriod $month, int $limit = 8): array
    {
        $rows = $this->uses($academyId, $meter, $month)->groupBy('user_id')
            ->selectRaw('user_id, -sum(amount) as used')->orderByDesc('used')->get();
        $names = DB::connection('pgsql_platform')->table('users')->where('academy_id', $academyId)
            ->whereIn('id', $rows->pluck('user_id')->filter()->all())->pluck('name', 'id');
        $known = [];
        $unknown = 0;
        foreach ($rows as $row) {
            $name = $row->user_id === null ? null : ($names[$row->user_id] ?? null);
            $name === null ? $unknown += (int) $row->used : $known[] = ['name' => (string) $name, 'used' => (int) $row->used];
        }
        $known = array_slice($known, 0, $limit);
        if ($unknown > 0) {
            $known[] = ['name' => null, 'used' => $unknown];
        }

        return $known;
    }

    /**
     * The ledger as the academy reads it, newest first: holds and their
     * releases left out, an overage moved onto a later lot shown once, and
     * the balance after each line (uses counted while the plan had no limit
     * leave it as it was).
     *
     * @return list<array{at: CarbonImmutable, kind: string, subject: ?string, ref_type: ?string, amount: int, after: int, expires_at: ?CarbonImmutable, overage: bool, reason: ?string}>
     */
    public function statement(string $academyId, Meter $meter, int $limit = 50, int $offset = 0, ?UsagePeriod $within = null): array
    {
        $counted = "(kind not in ('reserve', 'release') and (lot_id is not null or overage))";
        $lines = DB::connection('pgsql_platform')->table('usage_ledger')
            ->where('academy_id', $academyId)->where('meter', $meter->value)
            ->whereNotIn('kind', [Ledger::RESERVE, Ledger::RELEASE])
            ->selectRaw("id, created_at, occurred_at, kind, subject, ref_type, amount, expires_at, overage, reason, lot_id,
                sum(case when {$counted} then amount else 0 end) over (order by occurred_at, created_at, id) as after");
        $query = DB::connection('pgsql_platform')->query()->fromSub($lines, 'l')
            // An overage moved onto a later lot is one line (the lot's side), not two.
            ->where(fn ($q) => $q->where('ref_type', '<>', 'overage')->orWhereNull('ref_type')->orWhereNotNull('lot_id'))
            ->when($within, fn ($q) => $q->where('occurred_at', '>=', $within->from)->where('occurred_at', '<', $within->to))
            ->orderByDesc('occurred_at')->orderByDesc('created_at')->orderByDesc('id')->offset($offset)->limit($limit);

        return $query->get()->map(fn ($r) => [
            'at' => CarbonImmutable::parse($r->occurred_at),
            'kind' => (string) $r->kind,
            'subject' => $r->subject,
            'ref_type' => $r->ref_type,
            'amount' => (int) $r->amount,
            'after' => (int) $r->after,
            'expires_at' => $r->expires_at === null ? null : CarbonImmutable::parse($r->expires_at),
            'overage' => (bool) $r->overage,
            'reason' => $r->reason,
        ])->all();
    }

    public function statementCount(string $academyId, Meter $meter): int
    {
        return DB::connection('pgsql_platform')->table('usage_ledger')->where('academy_id', $academyId)->where('meter', $meter->value)
            ->whereNotIn('kind', [Ledger::RESERVE, Ledger::RELEASE])
            ->where(fn ($q) => $q->where('ref_type', '<>', 'overage')->orWhereNull('ref_type')->orWhereNotNull('lot_id'))
            ->count();
    }

    /** The uses of a month (not holds, not an overage moved onto a later lot). */
    private function uses(string $academyId, Meter $meter, UsagePeriod $month): Builder
    {
        return DB::connection('pgsql_platform')->table('usage_ledger')->where('academy_id', $academyId)->where('meter', $meter->value)
            ->where('kind', Ledger::CONSUME)->where(fn ($q) => $q->whereNull('ref_type')->orWhere('ref_type', '<>', 'overage'))
            ->where('occurred_at', '>=', $month->from)->where('occurred_at', '<', $month->to);
    }
}
