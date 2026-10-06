<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\BillingAccount;
use Modules\Billing\Models\PriceRegion;
use Modules\SharedKernel\Money\Money;
use Throwable;

/**
 * The academy's price regions (Gulf, Egypt, Europe, US…), each with one
 * currency; exactly one is the default. A region payers already use cannot
 * be removed, and its currency cannot change.
 */
final class SavePriceRegions
{
    /** @param  list<array{id?: ?string, name_ar: string, name_en: string, currency: string, is_default?: bool}>  $rows */
    public function handle(array $rows): void
    {
        if ($rows === []) {
            throw ValidationException::withMessages(['regions' => __('billing::plans.errors.one_region')]);
        }
        foreach ($rows as $row) {
            try {
                Money::decimals($row['currency']);
            } catch (Throwable) {
                throw ValidationException::withMessages(['regions' => __('billing::plans.errors.currency', ['currency' => $row['currency']])]);
            }
        }

        DB::transaction(function () use ($rows) {
            $keep = array_values(array_filter(array_column($rows, 'id')));
            $removed = PriceRegion::query()->whereNotIn('id', $keep)->pluck('id');
            if (BillingAccount::query()->whereIn('price_region_id', $removed)->exists()) {
                throw ValidationException::withMessages(['regions' => __('billing::plans.errors.region_in_use')]);
            }
            PriceRegion::query()->whereIn('id', $removed)->delete();

            $default = collect($rows)->search(fn (array $r) => (bool) ($r['is_default'] ?? false));
            foreach (array_values($rows) as $i => $row) {
                $region = isset($row['id']) && $row['id'] ? PriceRegion::query()->findOrFail($row['id']) : new PriceRegion;
                $currency = strtoupper($row['currency']);
                if ($region->exists && $region->currency !== $currency && BillingAccount::query()->where('price_region_id', $region->id)->exists()) {
                    throw ValidationException::withMessages(['regions' => __('billing::plans.errors.region_in_use')]);
                }
                $region->fill([
                    'name_ar' => trim($row['name_ar']),
                    'name_en' => trim($row['name_en']),
                    'currency' => $currency,
                    'is_default' => $i === ($default === false ? 0 : $default),
                    'sort' => $i,
                ])->save();
            }
        });
    }
}
