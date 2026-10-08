<?php

namespace Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A coupon on a subscription: its invoices come out with it taken off
 * until it has taken off as many as it may (counted from the invoices
 * themselves, so a voided invoice gives its turn back), or it is removed.
 *
 * @property string $id
 * @property string $coupon_id
 * @property string $billing_account_id
 * @property string $subscription_id
 * @property ?string $applied_by
 * @property CarbonImmutable $applied_at
 * @property ?string $removed_by
 * @property ?CarbonImmutable $removed_at
 */
final class CouponRedemption extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['coupon_id', 'billing_account_id', 'subscription_id', 'applied_by', 'applied_at', 'removed_by', 'removed_at'];

    protected function casts(): array
    {
        return ['applied_at' => 'immutable_datetime', 'removed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** The subscription's coupon now, if any. */
    public static function liveFor(string $subscriptionId): ?self
    {
        return self::query()->with('coupon')->where('subscription_id', $subscriptionId)->whereNull('removed_at')->first();
    }

    /** The invoices (issued or paid) it has taken something off. */
    public function used(): int
    {
        return (int) DB::table('invoice_lines as l')
            ->join('invoices as i', fn ($j) => $j->on('i.academy_id', '=', 'l.academy_id')->on('i.id', '=', 'l.invoice_id'))
            ->where('l.kind', 'discount')->whereRaw("l.meta->>'redemption_id' = ?", [$this->id])
            ->whereIn('i.status', ['issued', 'paid'])
            ->count();
    }

    /** Whether it still takes something off the next invoice. */
    public function hasTurnsLeft(): bool
    {
        $blocks = $this->coupon->blocks;

        return $this->removed_at === null && ($blocks === null || $this->used() < $blocks);
    }
}
