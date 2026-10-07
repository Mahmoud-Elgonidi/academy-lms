<?php

namespace Modules\Payroll\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A closed payroll month: from then on its entries never change and
 * anything decided later is paid in the next open month.
 *
 * @property string $id
 * @property CarbonImmutable $month
 * @property CarbonImmutable $closed_at
 * @property string $closed_by
 */
final class PayrollPeriod extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['month', 'closed_at', 'closed_by'];

    protected function casts(): array
    {
        return ['month' => 'immutable_date', 'closed_at' => 'immutable_datetime'];
    }
}
