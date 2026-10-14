<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Domain\BlockTerms;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;
use Modules\Billing\Models\Subscription;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Money\Money;

/**
 * Creates or updates a block plan and its price in each region. Prices are
 * typed as decimals in the region's currency and stored in minor units.
 * Subscriptions keep the price they were sold at, so a new price applies to
 * new enrolments only. A plan is per course or a shared package ("12
 * lessons in any subject"); which, and its shape (lesson length, lessons a
 * week and per block, which renewals and lesson prices read from it), are
 * fixed once anyone is on it: another shape is another plan.
 */
final class SavePlan
{
    /**
     * @param  array{name_ar: string, name_en: string, session_minutes: int|string, sessions_per_block: int|string, sessions_per_week: int|string,
     *               validity_days?: int|string|null, renew_at_sessions?: int|string|null, is_active?: bool, credit_scope?: ?string,
     *               prices?: list<array{price_region_id: string, amount: string|int|float|null}>}  $data
     */
    public function handle(?Plan $plan, array $data): Plan
    {
        // A locked field is not sent by the plan's form: it keeps what the plan has.
        $perBlock = (int) ($data['sessions_per_block'] ?? $plan?->sessions_per_block);
        $perWeek = (int) ($data['sessions_per_week'] ?? $plan?->sessions_per_week);
        $minutes = (int) ($data['session_minutes'] ?? $plan?->session_minutes);
        if ($perBlock <= 0 || $perWeek <= 0 || $minutes <= 0) {
            throw ValidationException::withMessages(['sessions_per_block' => __('billing::plans.errors.positive')]);
        }

        $scope = $data['credit_scope'] ?? $plan?->credit_scope ?? 'enrollment';
        if (! in_array($scope, ['enrollment', Plan::SHARED], true)) {
            throw ValidationException::withMessages(['credit_scope' => __('billing::plans.errors.scope')]);
        }
        if ($plan?->exists && Subscription::query()->where('plan_id', $plan->id)->exists()) {
            if ($scope !== $plan->credit_scope) {
                throw ValidationException::withMessages(['credit_scope' => __('billing::plans.errors.scope_locked')]);
            }
            if ([$minutes, $perWeek, $perBlock] !== [(int) $plan->session_minutes, (int) $plan->sessions_per_week, (int) $plan->sessions_per_block]) {
                throw ValidationException::withMessages(['session_minutes' => __('billing::plans.errors.shape_locked')]);
            }
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

        return DB::transaction(function () use ($plan, $data, $perBlock, $perWeek, $minutes, $prices, $scope) {
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
                'credit_scope' => $scope,
            ])->save();

            $plan->prices()->whereNotIn('price_region_id', array_keys($prices))->delete();
            foreach ($prices as $regionId => $money) {
                $plan->prices()->updateOrCreate(['price_region_id' => $regionId], ['amount_minor' => $money->minor, 'currency' => $money->currency]);
            }
            // What families will be billed: who set it, and when.
            app(AuditTrail::class)->record('billing.plan_saved', 'plan', $plan->id, [
                'name' => $plan->name(),
                'prices' => implode(' · ', array_map(fn (Money $m) => $m->format(), $prices)) ?: '—',
                'active' => $plan->is_active,
            ]);

            return $plan;
        });
    }
}
