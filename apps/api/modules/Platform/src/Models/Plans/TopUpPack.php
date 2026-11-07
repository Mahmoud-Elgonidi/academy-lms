<?php

namespace Modules\Platform\Models\Plans;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Plans\Limit;

/**
 * An extra pack the platform sells for one month: so many video minutes or
 * AI uses over the plan's. Set in the console (SaveTopUpPack); platform
 * connection only.
 *
 * @property string $id
 * @property string $limit
 * @property int $amount
 * @property string $name_ar
 * @property string $name_en
 * @property ?int $price_minor
 * @property ?string $currency
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
        return ['amount' => 'integer', 'price_minor' => 'integer', 'sort' => 'integer', 'archived_at' => 'immutable_datetime'];
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
