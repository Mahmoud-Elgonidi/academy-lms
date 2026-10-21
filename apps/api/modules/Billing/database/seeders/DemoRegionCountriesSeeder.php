<?php

namespace Modules\Billing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Billing\Models\PriceRegion;

/**
 * The demo's price regions name their countries, so its public page shows a
 * visitor the prices of theirs (by the region's currency, as DemoBillingSeeder
 * makes them). Once: a demo whose regions already name any country stays as
 * it is.
 */
final class DemoRegionCountriesSeeder extends Seeder
{
    /** By the region's currency. */
    public const COUNTRIES = [
        'SAR' => ['SA', 'KW', 'QA', 'BH', 'OM'],
        'AED' => ['AE'],
        'GBP' => ['GB'],
        'EUR' => ['DE', 'FR', 'NL', 'BE', 'IT', 'ES', 'IE', 'AT'],
        'USD' => ['US', 'CA'],
        'EGP' => ['EG'],
    ];

    public function run(): void
    {
        $regions = PriceRegion::query()->orderBy('sort')->get();
        if ($regions->contains(fn (PriceRegion $r) => ($r->countries ?? []) !== [])) {
            return;
        }
        $given = [];
        foreach ($regions as $region) {
            $countries = array_values(array_diff(self::COUNTRIES[$region->currency] ?? [], $given));
            if ($countries !== []) {
                $region->forceFill(['countries' => $countries])->save();
                $given = [...$given, ...$countries];
            }
        }
    }
}
