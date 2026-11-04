<?php

namespace Modules\Billing\Models;

use Carbon\CarbonImmutable;
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
 * @property bool $auto_renew renewals charged to the payer's saved card (J5b)
 * @property ?CarbonImmutable $auto_renew_changed_at
 */
final class BillingAccount extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['payer_id', 'price_region_id', 'currency', 'auto_renew', 'auto_renew_changed_at'];

    protected $attributes = ['auto_renew' => false];

    protected function casts(): array
    {
        return ['auto_renew' => 'boolean', 'auto_renew_changed_at' => 'immutable_datetime'];
    }
}
