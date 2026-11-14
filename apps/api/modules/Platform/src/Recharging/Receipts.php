<?php

namespace Modules\Platform\Recharging;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Domain\Recharging\ReceiptNumber;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\Recharging\PlatformReceipt;
use Modules\Platform\Models\Recharging\TopUpPurchase;

/**
 * The platform's receipt for a paid purchase (docs/architecture/02, section
 * 11, "الإيصالات"): the next number of the year under a lock (R-2026-000123),
 * the seller (the platform's legal name, Platform settings › Platform), the
 * academy, the pack, the amount, how it was paid and when, as they are now.
 * One per purchase (held by the database); never changed (trigger). Call it
 * inside the transaction that marks the purchase paid.
 */
final class Receipts
{
    public const LOCK = 'platform-receipts:';

    public function issue(TopUpPurchase $purchase): PlatformReceipt
    {
        $existing = PlatformReceipt::query()->where('purchase_id', $purchase->id)->first();
        if ($existing !== null) {
            return $existing;
        }
        $db = DB::connection('pgsql_platform');
        $paidAt = $purchase->paid_at ?? CarbonImmutable::now();
        $year = (int) $paidAt->utc()->format('Y');

        return $db->transaction(function () use ($db, $purchase, $paidAt, $year): PlatformReceipt {
            $db->select('select pg_advisory_xact_lock(hashtext(?))', [self::LOCK.$year]);
            $seq = (int) PlatformReceipt::query()->where('year', $year)->max('seq') + 1;
            $academy = Academy::query()->findOrFail($purchase->academy_id);
            $pack = $purchase->pack;

            return PlatformReceipt::query()->create([
                'academy_id' => $purchase->academy_id,
                'purchase_id' => $purchase->id,
                'number' => ReceiptNumber::format($year, $seq),
                'year' => $year,
                'seq' => $seq,
                'seller' => mb_substr((string) config('privacy.platform_name', config('app.name')), 0, 150),
                'academy_name' => mb_substr($academy->name_ar === $academy->name_en ? $academy->name_en : $academy->name_en.' / '.$academy->name_ar, 0, 200),
                'pack_name_ar' => mb_substr($pack?->name_ar ?? '—', 0, 80),
                'pack_name_en' => mb_substr($pack?->name_en ?? '—', 0, 80),
                'limit' => $purchase->limit,
                'amount' => $purchase->amount,
                'price_minor' => $purchase->price_minor,
                'currency' => $purchase->currency,
                'method' => $purchase->method,
                'paid_at' => $paidAt,
            ]);
        });
    }
}
