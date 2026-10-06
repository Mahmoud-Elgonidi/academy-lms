<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $id
 * @property string $name_ar
 * @property string $name_en
 * @property string $currency
 * @property list<string> $countries
 * @property bool $is_default
 * @property int $sort
 */
final class PriceRegion extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['name_ar', 'name_en', 'currency', 'countries', 'is_default', 'sort'];

    protected function casts(): array
    {
        return ['countries' => 'array', 'is_default' => 'boolean'];
    }

    public function name(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
