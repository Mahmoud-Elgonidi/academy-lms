<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Domain\BlockTerms;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\SharedKernel\Money\Money;

/**
 * Creates or updates a block plan and its price in each region. Prices are
 * typed as decimals in the region's currency and stored in minor units.
 * Subscriptions keep the price they were sold at, so a new price applies to
 * new enrolments only.
 */
final class SavePlan
{
    /**
     * @param  array{name_ar: string, name_en: string, session_minutes: int|string, sessions_per_block: int|string, sessions_per_week: int|string,
     *               validity_days?: int|string|null, renew_at_sessions?: int|string|null, is_active?: bool, prices?: list<array{price_region_id: string, amount: string|int|float|null}>}  $data
     */
    public function handle(?Plan $plan, array $data): Plan
    {
        $perBlock = (int) $data['sessions_per_block'];
        $perWeek = (int) $data['sessions_per_week'];
        $minutes = (int) $data['session_minutes'];
        if ($perBlock <= 0 || $perWeek <= 0 || $minutes <= 0) {
            throw ValidationException::withMessages(['sessions_per_block' => __('billing::plans.errors.positive')]);
        }

        $regions = PriceRegion::query()->pluck('currency', 'id');
        $prices = [];
        foreach ($data['prices'] ?? [] as $row) {
            if (($row['amount'] ?? '') === '' || $row['amount'] === null) {
                continue;
            }
            $currency = $regions[$row['price_region_id']] ?? null;
            if ($currency === null || isset($prices[$row['price_region_id']])) {
                throw ValidationException::withMessages(['prices' => __('billing::plans.errors.region_twice')]);
            }
            $money = Money::parse($row['amount'], $currency);
            if ($money->minor < 0) {
                throw ValidationException::withMessages(['prices' => __('billing::plans.errors.negative')]);
            }
            $prices[$row['price_region_id']] = $money;
        }

        return DB::transaction(function () use ($plan, $data, $perBlock, $perWeek, $minutes, $prices) {
            $plan ??= new Plan;
            $plan->fill([
                'name_ar' => trim($data['name_ar']),
                'name_en' => trim($data['name_en']),
                'session_minutes' => $minutes,
                'sessions_per_block' => $perBlock,
                'sessions_per_week' => $perWeek,
                'validity_days' => (int) ($data['validity_days'] ?? 0) ?: BlockTerms::defaultValidityDays($perBlock, $perWeek),
                'renew_at_sessions' => max(0, min($perBlock - 1, (int) ($data['renew_at_sessions'] ?? 2))),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            $plan->prices()->whereNotIn('price_region_id', array_keys($prices))->delete();
            foreach ($prices as $regionId => $money) {
                $plan->prices()->updateOrCreate(['price_region_id' => $regionId], ['amount_minor' => $money->minor, 'currency' => $money->currency]);
            }

            return $plan;
        });
    }
}
