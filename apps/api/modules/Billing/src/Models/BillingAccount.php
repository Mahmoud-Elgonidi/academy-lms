<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Who pays: a guardian for one or more children, or an adult student.
 *
 * @property string $id
 * @property string $payer_id
 * @property string $price_region_id
 * @property string $currency
 */
final class BillingAccount extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['payer_id', 'price_region_id', 'currency'];
}
