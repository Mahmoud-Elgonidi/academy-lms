<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $id
 * @property string $plan_id
 * @property string $price_region_id
 * @property int $amount_minor
 * @property string $currency
 */
final class PlanPrice extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['plan_id', 'price_region_id', 'amount_minor', 'currency'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    /** @return BelongsTo<PriceRegion, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(PriceRegion::class, 'price_region_id');
    }

    public function money(): Money
    {
        return Money::of($this->amount_minor, $this->currency);
    }
}
