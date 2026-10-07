<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Billing\Payments\GatewayManager;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * The academy's own account at a payment gateway: the academy is the
 * merchant (docs/architecture/03, section 5). The keys are stored encrypted
 * and never shown again; the webhook comes to a path of its own.
 *
 * @property string $id
 * @property string $gateway stripe | stripe_connect | moyasar | tap | paymob | demo
 * @property ?string $external_id a connected Stripe account (acct_…), no secret
 * @property string $label
 * @property string $mode test | live
 * @property list<string> $currencies empty: every currency the gateway takes
 * @property array<string, string> $credentials
 * @property bool $is_active
 * @property int $sort
 */
final class GatewayAccount extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const GATEWAYS = ['stripe', 'stripe_connect', 'moyasar', 'tap', 'paymob', 'demo'];

    protected $fillable = ['gateway', 'label', 'mode', 'currencies', 'credentials', 'is_active', 'sort', 'created_by', 'external_id'];

    protected $attributes = ['mode' => 'test', 'currencies' => '[]', 'is_active' => true, 'sort' => 0];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return ['currencies' => 'array', 'credentials' => 'encrypted:array', 'is_active' => 'boolean', 'sort' => 'integer'];
    }

    /** Whether this account takes payments in this currency: the ones the academy listed, else every one the gateway takes. */
    public function takes(string $currency): bool
    {
        $allowed = $this->currencies !== [] ? $this->currencies : GatewayManager::currencies($this->gateway);

        return $allowed === null || in_array(strtoupper($currency), $allowed, true);
    }
}
