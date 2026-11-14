<?php

namespace Modules\Platform\Models\Recharging;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\Meter;

/**
 * The platform's numbered receipt for one purchase (R-2026-000123): the
 * seller, the academy, the pack, the amount and how it was paid, as they
 * were when it was issued. Never changed (trigger). Platform connection only.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $purchase_id
 * @property string $number
 * @property int $year
 * @property int $seq
 * @property string $seller
 * @property string $academy_name
 * @property string $pack_name_ar
 * @property string $pack_name_en
 * @property string $limit
 * @property int $amount
 * @property int $price_minor
 * @property string $currency
 * @property string $method
 * @property CarbonImmutable $paid_at
 * @property CarbonImmutable $created_at
 */
final class PlatformReceipt extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $connection = 'pgsql_platform';

    protected $table = 'platform_receipts';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['year' => 'integer', 'seq' => 'integer', 'amount' => 'integer', 'price_minor' => 'integer', 'paid_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<TopUpPurchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(TopUpPurchase::class, 'purchase_id');
    }

    public function price(): Money
    {
        return Money::of($this->price_minor, $this->currency);
    }

    public function meter(): Meter
    {
        return Meter::fromLimit(Limit::from($this->limit)) ?? Meter::VideoMinutes;
    }

    public function packName(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->pack_name_ar : $this->pack_name_en;
    }
}
