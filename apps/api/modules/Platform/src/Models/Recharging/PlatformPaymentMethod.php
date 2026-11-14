<?php

namespace Modules\Platform\Models\Recharging;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * The academy's card saved with the platform's Stripe account, for automatic
 * recharge: only Stripe's references (customer, payment method), encrypted and
 * never shown or logged, and what is shown (brand, last four, expiry). One
 * live a academy; removed ones stay as rows. Platform connection only.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $customer_ref
 * @property string $method_ref
 * @property string $brand
 * @property string $last4
 * @property int $exp_month
 * @property int $exp_year
 * @property ?string $saved_by
 * @property ?CarbonImmutable $removed_at
 * @property ?string $removed_reason
 */
final class PlatformPaymentMethod extends Model
{
    use HasUuids;

    protected $connection = 'pgsql_platform';

    protected $table = 'platform_payment_methods';

    protected $guarded = [];

    protected $hidden = ['customer_ref', 'method_ref'];

    protected function casts(): array
    {
        return [
            'customer_ref' => 'encrypted', 'method_ref' => 'encrypted', 'exp_month' => 'integer', 'exp_year' => 'integer',
            'removed_at' => 'immutable_datetime',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('removed_at');
    }

    public static function liveFor(string $academyId): ?self
    {
        return self::query()->live()->where('academy_id', $academyId)->first();
    }

    /** The card's last month has ended (on UTC: Stripe's own reckoning). */
    public function expired(?CarbonImmutable $at = null): bool
    {
        $end = CarbonImmutable::create($this->exp_year, max(1, $this->exp_month), 1, 0, 0, 0, 'UTC')->addMonth();

        return ($at ?? CarbonImmutable::now())->greaterThanOrEqualTo($end);
    }

    public function expiry(): string
    {
        return sprintf('%02d/%02d', $this->exp_month, $this->exp_year % 100);
    }
}
