<?php

namespace Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A renewal invoice charged to the payer's saved card (docs/architecture/02,
 * section 27, J5b), one per invoice: told three days ahead (scheduled), then
 * charged once (charging), and paid; or a soft decline waits a day for its one
 * retry (retry), else failed; cancelled before the charge (turned off, the
 * card removed, paid another way, a proof, voided, the course paused or
 * ended); or skipped when it was never going to be charged (no card for the
 * currency, the card expires first).
 *
 * @property string $id
 * @property string $invoice_id
 * @property string $payer_id
 * @property ?string $payment_method_id
 * @property int $amount_minor
 * @property string $currency
 * @property string $status
 * @property int $attempts
 * @property ?CarbonImmutable $noticed_at
 * @property ?CarbonImmutable $charge_at
 * @property ?CarbonImmutable $next_attempt_at
 * @property ?string $checkout_id
 * @property ?string $failure
 * @property ?string $cancel_reason
 */
final class AutoCharge extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const SCHEDULED = 'scheduled';

    public const CHARGING = 'charging';

    public const RETRY = 'retry';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const SKIPPED = 'skipped';

    /** Still to come: the J5a timeline's day steps wait while one of these stands. */
    public const WAITING = [self::SCHEDULED, self::CHARGING, self::RETRY];

    protected $fillable = [
        'invoice_id', 'payer_id', 'payment_method_id', 'amount_minor', 'currency', 'status', 'attempts',
        'noticed_at', 'charge_at', 'next_attempt_at', 'checkout_id', 'failure', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer', 'attempts' => 'integer', 'noticed_at' => 'immutable_datetime',
            'charge_at' => 'immutable_datetime', 'next_attempt_at' => 'immutable_datetime',
        ];
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

    /** @return BelongsTo<PaymentMethod, $this> */
    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function money(): Money
    {
        return Money::of($this->amount_minor, $this->currency);
    }

    /** When it is charged next: the planned moment, or the retry's. */
    public function nextAt(): ?CarbonImmutable
    {
        return match ($this->status) {
            self::SCHEDULED => $this->charge_at,
            self::RETRY => $this->next_attempt_at,
            default => null,
        };
    }
}
