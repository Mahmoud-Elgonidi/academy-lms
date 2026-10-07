<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $academy_id
 * @property int $tax_rate_bp
 * @property bool $prices_include_tax
 * @property ?string $tax_number
 * @property string $invoice_prefix
 * @property int $due_days
 * @property ?string $manual_payment_note how to pay by hand (bank transfer, InstaPay…), shown with the invoice
 * @property ?string $seller_name the academy's name as registered for tax, on its invoices
 * @property bool $zatca Saudi simplified tax invoices with the QR code (ZATCA, phase 1)
 */
final class BillingSettings extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $table = 'billing_settings';

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = ['tax_rate_bp', 'prices_include_tax', 'tax_number', 'invoice_prefix', 'due_days', 'manual_payment_note', 'seller_name', 'zatca'];

    protected $attributes = ['tax_rate_bp' => 0, 'prices_include_tax' => true, 'invoice_prefix' => 'INV-', 'due_days' => 3, 'zatca' => false];

    protected function casts(): array
    {
        return ['tax_rate_bp' => 'integer', 'prices_include_tax' => 'boolean', 'due_days' => 'integer', 'zatca' => 'boolean'];
    }

    /** This academy's settings; the defaults when it has no row yet. */
    public static function current(): self
    {
        return self::query()->first() ?? new self;
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('academy_id', $this->getAttribute('academy_id'));
    }
}
