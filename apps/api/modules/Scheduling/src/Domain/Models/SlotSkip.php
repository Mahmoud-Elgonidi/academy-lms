<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A week a weekly time was set up without (J3: an academy holiday or the
 * teacher's leave among a self-started course's first weeks): its lesson is
 * never made (MaterializeSessions). The date is on the slot's clock.
 *
 * @property string $id
 * @property string $slot_id
 * @property CarbonImmutable $on_date
 * @property string $reason holiday or teacher_leave
 */
final class SlotSkip extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'slot_skips';

    protected $fillable = ['slot_id', 'on_date', 'reason'];

    protected function casts(): array
    {
        return ['on_date' => 'immutable_date', 'created_at' => 'immutable_datetime'];
    }
}
