<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $id
 * @property string $invoice_id
 * @property string $kind block | extra | adjustment
 * @property string $description_ar
 * @property string $description_en
 * @property int $quantity
 * @property int $unit_minor
 * @property int $amount_minor
 * @property array<string, mixed> $meta
 */
final class InvoiceLine extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['invoice_id', 'kind', 'description_ar', 'description_en', 'quantity', 'unit_minor', 'amount_minor', 'meta'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_minor' => 'integer', 'amount_minor' => 'integer', 'meta' => 'array'];
    }

    public function description(): string
    {
        return app()->getLocale() === 'ar' ? $this->description_ar : $this->description_en;
    }
}
