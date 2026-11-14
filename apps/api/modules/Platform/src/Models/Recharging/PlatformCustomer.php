<?php

namespace Modules\Platform\Models\Recharging;

use Illuminate\Database\Eloquent\Model;

/**
 * The academy as a customer of the platform's Stripe account (cus_…,
 * encrypted, never shown). Platform connection only.
 *
 * @property string $academy_id
 * @property string $customer_ref
 */
final class PlatformCustomer extends Model
{
    protected $connection = 'pgsql_platform';

    protected $table = 'platform_customers';

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $hidden = ['customer_ref'];

    protected function casts(): array
    {
        return ['customer_ref' => 'encrypted'];
    }
}
