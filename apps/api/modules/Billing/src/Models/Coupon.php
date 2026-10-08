<?php

namespace Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Domain\CouponTerms;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A code staff give out (docs/architecture/02, section 3, "الخصومات"): a
 * percentage or an amount off a subscription's invoices, the first N or
 * every one, within its dates, up to a number of uses, once per payer.
 * Turning it off stops new uses; those already on it keep it.
 *
 * @property string $id
 * @property string $code
 * @property ?string $name
 * @property string $kind percent | amount
 * @property ?int $percent_bp
 * @property ?int $amount_minor
 * @property ?string $currency
 * @property ?int $blocks the invoices it takes off; null: every one
 * @property ?CarbonImmutable $starts_on
 * @property ?CarbonImmutable $ends_on
 * @property ?int $max_uses
 * @property list<string> $plan_ids empty: any plan
 * @property bool $with_sibling
 * @property bool $is_active
 * @property ?string $created_by
 */
final class Coupon extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['code', 'name', 'kind', 'percent_bp', 'amount_minor', 'currency', 'blocks', 'starts_on', 'ends_on', 'max_uses', 'plan_ids', 'with_sibling', 'is_active', 'created_by'];

    protected $attributes = ['plan_ids' => '[]', 'with_sibling' => false, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'percent_bp' => 'integer',
            'amount_minor' => 'integer',
            'blocks' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'max_uses' => 'integer',
            'plan_ids' => 'array',
            'with_sibling' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Codes are kept in capitals without spaces: " ramadan 10" is RAMADAN10. */
    public static function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $code));
    }

    public static function byCode(string $code): ?self
    {
        return self::query()->where('code', self::normalize($code))->first();
    }

    /** @return HasMany<CouponRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function terms(): CouponTerms
    {
        return $this->kind === 'percent'
            ? CouponTerms::percent((int) $this->percent_bp, $this->with_sibling)
            : CouponTerms::amount((int) $this->amount_minor, $this->with_sibling);
    }

    /** "10%" or "50 ر.س." */
    public function valueLabel(): string
    {
        return $this->kind === 'percent'
            ? self::rate((int) $this->percent_bp)
            : Money::of((int) $this->amount_minor, (string) $this->currency)->format();
    }

    public static function rate(int $bp): string
    {
        return rtrim(rtrim(number_format($bp / 100, 2, '.', ''), '0'), '.').'%';
    }

    /**
     * Who it counts as used by: every payer still on it, and those who came
     * off it after it had taken something off an invoice.
     */
    public function uses(): int
    {
        return $this->counted()->count();
    }

    /** @return HasMany<CouponRedemption, $this> the redemptions that count as a use */
    public function counted(): HasMany
    {
        return $this->redemptions()
            ->where(fn ($q) => $q->whereNull('removed_at')->orWhereExists(fn ($e) => $e->select(DB::raw(1))
                ->from('invoice_lines as l')
                ->join('invoices as i', fn ($j) => $j->on('i.academy_id', '=', 'l.academy_id')->on('i.id', '=', 'l.invoice_id'))
                ->whereColumn('l.academy_id', 'coupon_redemptions.academy_id')
                ->where('l.kind', 'discount')
                ->whereRaw("l.meta->>'redemption_id' = coupon_redemptions.id::text")
                ->whereIn('i.status', ['issued', 'paid'])));
    }

    public function forPlan(string $planId): bool
    {
        return $this->plan_ids === [] || in_array($planId, $this->plan_ids, true);
    }
}
