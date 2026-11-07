<?php

namespace Modules\Platform\Plans;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Plans\Limit;

/**
 * What the platform team turned on over an academy's plan for one month:
 * the active extra packs (usage_topups), summed by limit. They count only in
 * their month (`period`, its first day on the academy's clock), so the next
 * month starts again from the plan's own amount.
 */
final class Extras
{
    /** @return array<string, int> Limit value => extra amount in that month */
    public static function of(string $academyId, string $periodKey): array
    {
        return DB::connection('pgsql_platform')->table('usage_topups')
            ->where('academy_id', $academyId)->where('period', $periodKey)->where('status', 'active')
            ->groupBy('limit')->selectRaw('"limit", sum(amount) as amount')->pluck('amount', 'limit')
            ->map(fn ($n) => (int) $n)->all();
    }

    public static function amount(string $academyId, Limit $limit, string $periodKey): int
    {
        return self::of($academyId, $periodKey)[$limit->value] ?? 0;
    }
}
