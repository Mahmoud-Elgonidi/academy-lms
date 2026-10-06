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
 */
final class BillingSettings extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $table = 'billing_settings';

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = ['tax_rate_bp', 'prices_include_tax', 'tax_number', 'invoice_prefix', 'due_days'];

    protected $attributes = ['tax_rate_bp' => 0, 'prices_include_tax' => true, 'invoice_prefix' => 'INV-', 'due_days' => 3];

    protected function casts(): array
    {
        return ['tax_rate_bp' => 'integer', 'prices_include_tax' => 'boolean', 'due_days' => 'integer'];
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
