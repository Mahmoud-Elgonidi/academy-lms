<?php

namespace Modules\Platform\Models\Plans;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\SharedKernel\Plans\Limit;

/**
 * An academy's request for an extra pack, and its answer: requested, then
 * active (counted in its month, `period`), declined (with the reason the
 * academy reads) or cancelled by the academy. Answered once (trigger); who
 * asked by id only. Platform connection only.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $limit
 * @property int $amount
 * @property string $period the month's first day it counts in (Y-m-d), as usage_counters
 * @property string $status
 * @property ?string $pack_id
 * @property ?string $requested_by
 * @property ?string $note
 * @property ?string $decided_by
 * @property ?CarbonImmutable $decided_at
 * @property ?string $reason
 * @property CarbonImmutable $created_at
 * @property ?TopUpPack $pack
 */
final class UsageTopUp extends Model
{
    use HasUuids;

    public const STATUSES = ['requested', 'active', 'declined', 'cancelled'];

    protected $connection = 'pgsql_platform';

    protected $table = 'usage_topups';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'decided_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<TopUpPack, $this> */
    public function pack(): BelongsTo
    {
        return $this->belongsTo(TopUpPack::class, 'pack_id');
    }

    public function limitKind(): Limit
    {
        return Limit::from($this->limit);
    }
}
