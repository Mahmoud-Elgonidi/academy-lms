<?php

namespace Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Once issued an invoice never changes except for what is paid on it; a
 * mistake is voided (unpaid). A Saudi tax invoice (ZATCA) is cancelled by a
 * credit note. What it says about the seller is kept as issued.
 *
 * @property string $id
 * @property ?string $number
 * @property string $billing_account_id
 * @property ?string $subscription_id
 * @property string $status draft | issued | paid | void
 * @property string $currency
 * @property int $subtotal_minor
 * @property int $tax_minor
 * @property int $total_minor
 * @property int $paid_minor
 * @property int $tax_rate_bp
 * @property bool $prices_include_tax
 * @property ?CarbonImmutable $issued_at
 * @property ?CarbonImmutable $due_at
 * @property ?CarbonImmutable $paid_at
 * @property ?CarbonImmutable $voided_at
 * @property ?string $void_reason
 * @property ?string $voided_by
 * @property ?string $seller_name
 * @property ?string $seller_tax_number
 * @property ?string $tax_document simplified (a ZATCA simplified tax invoice) | null
 * @property ?string $zatca_qr
 * @property ?string $credit_note_id
 */
final class Invoice extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'number', 'billing_account_id', 'subscription_id', 'status', 'currency', 'subtotal_minor', 'tax_minor', 'total_minor',
        'paid_minor', 'tax_rate_bp', 'prices_include_tax', 'issued_at', 'due_at', 'paid_at', 'voided_at', 'void_reason', 'voided_by', 'created_by',
        'seller_name', 'seller_tax_number', 'tax_document', 'zatca_qr', 'credit_note_id',
    ];

    /** What an issued invoice says, which never changes after. */
    private const FROZEN = ['number', 'currency', 'subtotal_minor', 'tax_minor', 'total_minor', 'tax_rate_bp', 'prices_include_tax', 'issued_at', 'seller_name', 'seller_tax_number', 'tax_document', 'zatca_qr'];

    protected $attributes = ['status' => 'draft', 'paid_minor' => 0];

    protected function casts(): array
    {
        return [
            'subtotal_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'paid_minor' => 'integer',
            'tax_rate_bp' => 'integer',
            'prices_include_tax' => 'boolean',
            'issued_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
        ];
    }

    public static function lock(string $id): self
    {
        return self::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('received_at');
    }

    /** @return BelongsTo<BillingAccount, $this> */
    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class);
    }

    /** @return BelongsTo<CreditNote, $this> */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function total(): Money
    {
        return Money::of($this->total_minor, $this->currency);
    }

    public function outstanding(): Money
    {
        return Money::of($this->total_minor - $this->paid_minor, $this->currency);
    }

    public function isOverdue(?CarbonImmutable $now = null): bool
    {
        return $this->status === 'issued' && $this->due_at !== null && $this->due_at->lessThan($now ?? CarbonImmutable::now());
    }

    public function save(array $options = []): bool
    {
        if ($this->exists && in_array($this->getOriginal('status'), ['issued', 'paid', 'void'], true) && $this->isDirty(self::FROZEN)) {
            throw new LogicException('An issued invoice does not change.');
        }

        return parent::save($options);
    }
}
