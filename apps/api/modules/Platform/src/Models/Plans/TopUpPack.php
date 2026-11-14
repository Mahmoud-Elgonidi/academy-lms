<?php

namespace Modules\Platform\Models\Plans;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Plans\Limit;

/**
 * An extra pack the platform sells: so many video minutes or AI credits over
 * the plan's, a purchase lot that lasts 12 months. Its price is set per
 * currency (prices: {"SAR": 9900}, minor units); an academy whose currency
 * has no price asks for it by transfer. Set in the console (SaveTopUpPack);
 * platform connection only.
 *
 * @property string $id
 * @property string $limit
 * @property int $amount
 * @property string $name_ar
 * @property string $name_en
 * @property array<string, int> $prices currency => minor units
 * @property int $sort
 * @property ?CarbonImmutable $archived_at
 */
final class TopUpPack extends Model
{
    use HasUuids;

    protected $connection = 'pgsql_platform';

    protected $table = 'saas_topup_packs';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'sort' => 'integer', 'archived_at' => 'immutable_datetime'];
    }

    /** Kept as a JSON object even when empty ({} never []). */
    protected function prices(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): array {
                $prices = array_map('intval', (array) json_decode((string) ($value ?? '{}'), true));
                ksort($prices);

                return $prices;
            },
            set: fn (?array $value) => json_encode((object) array_map('intval', $value ?? [])),
        );
    }

    /** Its price in the currency, or null when it has none there (bought by transfer only). */
    public function priceIn(string $currency): ?Money
    {
        $minor = ($this->prices ?? [])[strtoupper($currency)] ?? null;

        return $minor === null ? null : Money::of((int) $minor, strtoupper($currency));
    }

    /** @return list<Money> its prices, in the order they were set */
    public function priceList(): array
    {
        return array_values(array_map(fn (string $currency, $minor) => Money::of((int) $minor, $currency), array_keys($this->prices ?? []), $this->prices ?? []));
    }

    /** "SAR 99.00 · USD 26.00", or null with no price. */
    public function pricesText(): ?string
    {
        $list = $this->priceList();

        return $list === [] ? null : implode(' · ', array_map(fn (Money $m) => $m->format(), $list));
    }

    public function limitKind(): Limit
    {
        return Limit::from($this->limit);
    }

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar' ? $this->name_ar : $this->name_en;
    }

    /** @param  Builder<self>  $query */
    public function scopeOffered(Builder $query): void
    {
        $query->whereNull('archived_at');
    }
}
