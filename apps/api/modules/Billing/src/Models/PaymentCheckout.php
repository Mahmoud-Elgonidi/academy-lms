<?php

namespace Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One go at paying an invoice on a gateway's hosted page. Open until the
 * gateway says it is paid, failed or expired; paid means a Payment was
 * recorded for it (once: one payment per checkout). Money that came in for
 * an invoice already settled another way is "refund_due".
 *
 * @property string $id
 * @property string $invoice_id
 * @property string $gateway_account_id
 * @property ?string $payer_id
 * @property string $channel family | link
 * @property int $amount_minor
 * @property string $currency
 * @property string $status open | paid | failed | expired | refund_due
 * @property ?string $provider_ref
 * @property ?string $provider_payment_ref
 * @property ?string $url
 * @property ?string $failure
 * @property CarbonImmutable $expires_at
 * @property ?CarbonImmutable $paid_at
 * @property CarbonImmutable $created_at
 */
final class PaymentCheckout extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const STATUSES = ['open', 'paid', 'failed', 'expired', 'refund_due'];

    protected $fillable = [
        'invoice_id', 'gateway_account_id', 'payer_id', 'channel', 'amount_minor', 'currency', 'status',
        'provider_ref', 'provider_payment_ref', 'url', 'failure', 'expires_at', 'paid_at',
    ];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'expires_at' => 'immutable_datetime', 'paid_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    public static function lock(string $id): self
    {
        return self::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<GatewayAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(GatewayAccount::class, 'gateway_account_id');
    }

    public function money(): Money
    {
        return Money::of($this->amount_minor, $this->currency);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
