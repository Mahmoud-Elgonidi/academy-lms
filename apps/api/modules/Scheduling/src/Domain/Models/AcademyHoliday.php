<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Days the academy is closed (Eid, a national day): local days on the
 * academy's clock (docs/architecture/02, section 1). Lessons in them are
 * cancelled under rule 15 and none are generated there.
 *
 * @property string $id
 * @property string $name
 * @property string $timezone
 * @property CarbonImmutable $first_day
 * @property CarbonImmutable $last_day
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int $cancelled_count
 * @property ?string $created_by
 */
final class AcademyHoliday extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['name', 'timezone', 'first_day', 'last_day', 'starts_at', 'ends_at', 'cancelled_count', 'created_by'];

    protected function casts(): array
    {
        return [
            'first_day' => 'immutable_date',
            'last_day' => 'immutable_date',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'cancelled_count' => 'integer',
        ];
    }

    public function days(): int
    {
        return (int) $this->first_day->diffInDays($this->last_day) + 1;
    }

    /** Does a lesson starting at this instant fall on one of these days? */
    public function contains(CarbonImmutable $at): bool
    {
        return $at->greaterThanOrEqualTo($this->starts_at) && $at->lessThan($this->ends_at);
    }
}
