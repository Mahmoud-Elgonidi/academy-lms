<?php

namespace Modules\Credits\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Credits\Contracts\LotSource;
use Modules\Credits\Domain\LotBalance;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $id
 * @property string $account_id
 * @property LotSource $source
 * @property ?string $source_id
 * @property int $granted_min
 * @property int $remaining_min
 * @property ?CarbonImmutable $expires_at
 * @property CarbonImmutable $created_at
 */
final class CreditLot extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['account_id', 'source', 'source_id', 'granted_min', 'remaining_min', 'expires_at'];

    protected function casts(): array
    {
        return [
            'source' => LotSource::class,
            'granted_min' => 'integer',
            'remaining_min' => 'integer',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function toBalance(): LotBalance
    {
        return new LotBalance($this->id, $this->source->value, $this->remaining_min, $this->expires_at, $this->created_at);
    }
}
