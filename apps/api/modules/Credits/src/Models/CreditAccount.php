<?php

namespace Modules\Credits\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Totals derived from the ledger, updated with every entry under a row lock.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $student_id
 * @property string $scope_type
 * @property ?string $scope_id
 * @property int $available_min
 * @property int $reserved_min
 * @property int $consumed_min
 * @property ?int $low_balance_min
 * @property int $version
 */
final class CreditAccount extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['student_id', 'scope_type', 'scope_id', 'low_balance_min'];

    protected $attributes = ['available_min' => 0, 'reserved_min' => 0, 'consumed_min' => 0, 'version' => 1];

    protected function casts(): array
    {
        return [
            'available_min' => 'integer',
            'reserved_min' => 'integer',
            'consumed_min' => 'integer',
            'low_balance_min' => 'integer',
        ];
    }

    public static function lock(string $id): self
    {
        return self::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /** @return HasMany<CreditLot, $this> */
    public function lots(): HasMany
    {
        return $this->hasMany(CreditLot::class, 'account_id');
    }

    public function remainingMin(): int
    {
        return $this->available_min + $this->reserved_min;
    }
}
