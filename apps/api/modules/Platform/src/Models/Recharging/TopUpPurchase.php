<?php

namespace Modules\Platform\Models\Recharging;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Platform\Models\Plans\TopUpPack;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\Meter;

/**
 * One purchase of a pack by an academy (docs/architecture/02, section 11,
 * "الشحن"): a Stripe Checkout page (card), a charge on the saved card (auto)
 * or a transfer the platform team turned on (transfer). Open, then paid,
 * failed or expired, once (trigger); paid adds its lot once and gets one
 * receipt. Platform connection only.
 *
 * @property string $id
 * @property string $academy_id
 * @property ?string $pack_id
 * @property string $limit
 * @property int $amount
 * @property int $price_minor
 * @property string $currency
 * @property string $method
 * @property string $status
 * @property ?string $provider_ref
 * @property ?string $payment_ref
 * @property bool $save_card
 * @property ?string $payment_method_id
 * @property int $attempt
 * @property ?string $retry_of
 * @property ?string $failure
 * @property ?string $requested_by
 * @property ?string $topup_id
 * @property ?string $lot_id
 * @property ?CarbonImmutable $paid_at
 * @property ?CarbonImmutable $failed_at
 * @property CarbonImmutable $created_at
 * @property ?TopUpPack $pack
 * @property ?PlatformReceipt $receipt
 */
final class TopUpPurchase extends Model
{
    use HasUuids;

    public const CARD = 'card';

    public const AUTO = 'auto';

    public const TRANSFER = 'transfer';

    protected $connection = 'pgsql_platform';

    protected $table = 'topup_purchases';

    protected $guarded = [];

    /** @var array<string, mixed> the database's defaults, known before the row is read back */
    protected $attributes = ['status' => 'open', 'save_card' => false, 'attempt' => 1];

    protected function casts(): array
    {
        return [
            'amount' => 'integer', 'price_minor' => 'integer', 'save_card' => 'boolean', 'attempt' => 'integer',
            'paid_at' => 'immutable_datetime', 'failed_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<TopUpPack, $this> */
    public function pack(): BelongsTo
    {
        return $this->belongsTo(TopUpPack::class, 'pack_id');
    }

    /** @return HasOne<PlatformReceipt, $this> */
    public function receipt(): HasOne
    {
        return $this->hasOne(PlatformReceipt::class, 'purchase_id');
    }

    public function meter(): Meter
    {
        return Meter::fromLimit(Limit::from($this->limit)) ?? Meter::VideoMinutes;
    }

    public function price(): Money
    {
        return Money::of($this->price_minor, $this->currency);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
