<?php

namespace Modules\Platform\Models\Recharging;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Platform\Models\Plans\TopUpPack;
use Modules\SharedKernel\Plans\Meter;

/**
 * An academy's automatic recharge of one meter: when the balance falls below
 * the threshold, buy the pack on the saved card, at most monthly_cap times a
 * calendar month on the academy's clock. Turned off by the academy, or by
 * itself after a decline (off_reason). Platform connection only.
 *
 * @property string $academy_id
 * @property string $meter
 * @property string $pack_id
 * @property int $threshold
 * @property int $monthly_cap
 * @property bool $enabled
 * @property ?string $updated_by
 * @property ?string $off_reason
 * @property ?CarbonImmutable $off_at
 * @property ?TopUpPack $pack
 */
final class AutoRecharge extends Model
{
    protected $connection = 'pgsql_platform';

    protected $table = 'auto_recharges';

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['threshold' => 'integer', 'monthly_cap' => 'integer', 'enabled' => 'boolean', 'off_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<TopUpPack, $this> */
    public function pack(): BelongsTo
    {
        return $this->belongsTo(TopUpPack::class, 'pack_id');
    }

    public function meterKind(): Meter
    {
        return Meter::from($this->meter);
    }

    public static function for(string $academyId, Meter $meter): ?self
    {
        return self::query()->where('academy_id', $academyId)->where('meter', $meter->value)->first();
    }

    /** Two-column key: saved by its academy and meter. */
    protected function setKeysForSaveQuery($query)
    {
        return $query->where('academy_id', $this->getAttribute('academy_id'))->where('meter', $this->getAttribute('meter'));
    }
}
