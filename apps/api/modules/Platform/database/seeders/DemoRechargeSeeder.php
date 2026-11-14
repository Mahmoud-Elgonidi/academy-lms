<?php

namespace Modules\Platform\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Domain\Metering\Ledger;
use Modules\Platform\Models\Plans\TopUpPack;
use Modules\Platform\Models\Recharging\PlatformReceipt;
use Modules\Platform\Models\Recharging\TopUpPurchase;
use Modules\Platform\Recharging\Receipts;
use Modules\SharedKernel\Plans\Meter;

/**
 * The demo academy's purchases (docs/architecture/02, section 11, "الشحن"):
 * the two packs DemoUsageSeeder bought earlier this month, as transfers the
 * platform team turned on, each with its numbered receipt, so «المشتريات»
 * and the console's revenue read like a platform at work. The packs' own
 * prices are the platform's to set; the demo's receipts carry what a demo
 * academy paid. Once: nothing when the demo already has a receipt, or no demo.
 */
final class DemoRechargeSeeder extends Seeder
{
    /** What the demo paid for each pack, by transfer (SAR, minor units). */
    private const PAID = [Meter::VideoMinutes->value => 5000, Meter::AiCredits->value => 9000];

    public function run(TenantDirectory $academies, Receipts $receipts): void
    {
        $demo = $academies->bySlug('demo');
        if ($demo === null || PlatformReceipt::query()->where('academy_id', $demo->id)->exists()) {
            return;
        }
        $db = DB::connection('pgsql_platform');
        $currency = (string) ($db->table('academies')->where('id', $demo->id)->value('base_currency') ?: 'SAR');
        foreach (Meter::cases() as $meter) {
            $lot = $db->table('usage_ledger')->where('academy_id', $demo->id)->where('meter', $meter->value)->where('kind', Ledger::PURCHASE)
                ->where('ref_type', 'demo')->where('ref_id', 'pack-'.$meter->value)->first(['id', 'amount', 'occurred_at']);
            if ($lot === null) {
                continue;
            }
            $pack = TopUpPack::query()->where('limit', $meter->limit()->value)->orderByRaw('abs(amount - ?)', [(int) $lot->amount])->first();
            $price = $pack?->priceIn($currency);
            $purchase = TopUpPurchase::query()->create([
                'academy_id' => $demo->id, 'pack_id' => $pack?->id, 'limit' => $meter->limit()->value, 'amount' => (int) $lot->amount,
                'price_minor' => $price->minor ?? self::PAID[$meter->value], 'currency' => $price->currency ?? $currency,
                'method' => TopUpPurchase::TRANSFER, 'status' => 'paid', 'lot_id' => $lot->id, 'paid_at' => CarbonImmutable::parse($lot->occurred_at),
            ]);
            $receipts->issue($purchase);
        }
    }
}
