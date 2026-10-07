<?php

namespace Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Cancels an issued tax invoice in full (ZATCA: an invoice is never
 * deleted or changed; a credit note says it no longer stands). Its own
 * gapless number, the invoice it cancels, why, and the same QR fields.
 * Never changes once issued (a trigger holds it).
 *
 * @property string $id
 * @property string $number
 * @property string $invoice_id
 * @property string $reason
 * @property string $currency
 * @property int $subtotal_minor
 * @property int $tax_minor
 * @property int $total_minor
 * @property int $tax_rate_bp
 * @property ?string $seller_name
 * @property ?string $seller_tax_number
 * @property ?string $zatca_qr
 * @property CarbonImmutable $issued_at
 */
final class CreditNote extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'subtotal_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'tax_rate_bp' => 'integer',
            'issued_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function total(): Money
    {
        return Money::of($this->total_minor, $this->currency);
    }
}
