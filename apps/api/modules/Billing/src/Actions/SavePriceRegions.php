<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\BillingAccount;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Models\PriceRegion;
use Modules\SharedKernel\Geo\Countries;
use Modules\SharedKernel\Money\Money;
use Throwable;

/**
 * The academy's price regions (Gulf, Egypt, Europe, US…), each with one
 * currency; exactly one is the default. A region payers already use cannot
 * be removed, and its currency cannot change; nor can the currency of one
 * its plans are priced in. A region names the countries its families live
 * in (ISO codes from SharedKernel\Geo\Countries), each country in one region
 * at most: the academy's page shows a visitor the prices of their country's.
 */
final class SavePriceRegions
{
    /** @param  list<array{id?: ?string, name_ar: string, name_en: string, currency: string, is_default?: bool, countries?: list<string>}>  $rows */
    public function handle(array $rows): void
    {
        if ($rows === []) {
            throw ValidationException::withMessages(['regions' => __('billing::plans.errors.one_region')]);
        }
        foreach ($rows as $i => $row) {
            try {
                Money::decimals($row['currency']);
            } catch (Throwable) {
                throw ValidationException::withMessages(['regions' => __('billing::plans.errors.currency', ['currency' => $row['currency']])]);
            }
            // A row that does not say its countries keeps the ones it has.
            if (array_key_exists('countries', $row)) {
                $countries = [];
                foreach ((array) $row['countries'] as $code) {
                    $code = strtoupper(trim((string) $code));
                    if (! Countries::isKnown($code)) {
                        throw ValidationException::withMessages(['regions' => __('billing::plans.errors.country_unknown', ['country' => $code])]);
                    }
                    $countries[$code] = $code;
                }
                $rows[$i]['countries'] = array_values($countries);
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
            $taken = [];
            foreach (array_values($rows) as $i => $row) {
                $region = isset($row['id']) && $row['id'] ? PriceRegion::query()->findOrFail($row['id']) : new PriceRegion;
                $currency = strtoupper($row['currency']);
                if ($region->exists && $region->currency !== $currency && BillingAccount::query()->where('price_region_id', $region->id)->exists()) {
                    throw ValidationException::withMessages(['regions' => __('billing::plans.errors.region_in_use')]);
                }
                // Its plans' prices are amounts in the old currency (a dinar has three decimals, a riyal two): they are not carried over.
                if ($region->exists && $region->currency !== $currency && PlanPrice::query()->where('price_region_id', $region->id)->exists()) {
                    throw ValidationException::withMessages(['regions' => __('billing::plans.errors.region_priced', ['currency' => $region->currency])]);
                }
                $countries = $row['countries'] ?? ($region->exists ? array_values((array) $region->countries) : []);
                foreach ($countries as $code) {
                    if (isset($taken[$code])) {
                        throw ValidationException::withMessages(['regions' => __('billing::plans.errors.country_twice', ['country' => Countries::name($code, app()->getLocale())])]);
                    }
                    $taken[$code] = true;
                }
                $region->fill([
                    'name_ar' => trim($row['name_ar']),
                    'name_en' => trim($row['name_en']),
                    'currency' => $currency,
                    'countries' => $countries,
                    'is_default' => $i === ($default === false ? 0 : $default),
                    'sort' => $i,
                ])->save();
            }
        });
    }
}
