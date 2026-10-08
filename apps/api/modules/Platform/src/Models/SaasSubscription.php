<?php

namespace Modules\Platform\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The plan an academy is on, from when, with the platform team's changes for
 * this academy alone (its own limits, areas added or taken away). A new plan
 * or new terms end this row and start another; the trigger keeps the rest.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $plan_id
 * @property array<string, ?int> $limits
 * @property list<string> $features_on
 * @property list<string> $features_off
 * @property ?string $reason
 * @property ?string $changed_by
 * @property CarbonImmutable $started_at
 * @property ?CarbonImmutable $ended_at
 * @property SaasPlan $plan
 */
final class SaasSubscription extends Model
{
    use HasUuids;

    protected $connection = 'pgsql_platform';

    protected $table = 'saas_subscriptions';

    protected $guarded = [];

    protected $attributes = ['limits' => '{}', 'features_on' => '[]', 'features_off' => '[]'];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'features_on' => 'array',
            'features_off' => 'array',
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<SaasPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'plan_id');
    }

    public static function currentFor(string $academyId): ?self
    {
        return self::query()->with('plan')->where('academy_id', $academyId)->whereNull('ended_at')->first();
    }
}
