<?php

namespace Modules\Platform\Models\Plans;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Platform\Models\SaasPlan;

/**
 * An academy asks for a bigger plan (the one it would like, if it chose,
 * and a note); the platform team changes the plan in the console and marks
 * it handled. One open at a time; answered once (trigger). Platform
 * connection only.
 *
 * @property string $id
 * @property string $academy_id
 * @property ?string $plan_id
 * @property ?string $from_plan_id
 * @property string $status
 * @property ?string $requested_by
 * @property ?string $note
 * @property ?string $handled_by
 * @property ?CarbonImmutable $handled_at
 * @property CarbonImmutable $created_at
 * @property ?SaasPlan $plan
 * @property ?SaasPlan $fromPlan
 */
final class PlanUpgradeRequest extends Model
{
    use HasUuids;

    protected $connection = 'pgsql_platform';

    protected $table = 'plan_upgrade_requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['handled_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<SaasPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'plan_id');
    }

    /** @return BelongsTo<SaasPlan, $this> */
    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'from_plan_id');
    }

    public static function openFor(string $academyId): ?self
    {
        return self::query()->with('plan')->where('academy_id', $academyId)->where('status', 'requested')->first();
    }
}
