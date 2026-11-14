<?php

namespace Modules\Platform\Recharging;

use Modules\Platform\Models\Recharging\TopUpPurchase;
use Modules\SharedKernel\Plans\Meter;
use Modules\SharedKernel\Plans\UsagePeriod;

/** What the automatic recharge of an academy's meter bought this month, on its clock. */
final class RechargeStatus
{
    /** Automatic purchases this month that count toward the cap (paid, or under way). */
    public static function boughtThisMonth(string $academyId, Meter $meter, UsagePeriod $month): int
    {
        return TopUpPurchase::query()->where('academy_id', $academyId)->where('limit', $meter->limit()->value)
            ->where('method', TopUpPurchase::AUTO)->whereIn('status', ['paid', 'open'])
            ->where('created_at', '>=', $month->from)->where('created_at', '<', $month->to)->count();
    }

    public static function lastAuto(string $academyId, Meter $meter): ?TopUpPurchase
    {
        return TopUpPurchase::query()->where('academy_id', $academyId)->where('limit', $meter->limit()->value)
            ->where('method', TopUpPurchase::AUTO)->orderByDesc('created_at')->orderByDesc('attempt')->first();
    }
}
