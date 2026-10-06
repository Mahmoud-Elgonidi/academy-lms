<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Scheduling\Domain\Shifts\ShiftBlock;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One approved version of a teacher's weekly schedule, in force for
 * `effective` (a daterange, upper bound open while it is current).
 *
 * @property string $id
 * @property string $teacher_id
 * @property int $version
 * @property string $timezone
 * @property string $status
 * @property string $effective
 * @property ?string $approved_by
 * @property ?string $source_proposal_id
 */
final class ShiftSchedule extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'teacher_shift_schedules';

    protected $fillable = ['teacher_id', 'version', 'timezone', 'status', 'effective', 'approved_by', 'source_proposal_id'];

    /** @return HasMany<ShiftRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(ShiftRule::class, 'schedule_id');
    }

    public function shifts(): WeeklyShifts
    {
        return new WeeklyShifts(
            $this->rules->map(fn (ShiftRule $r) => new ShiftBlock($r->weekday, ShiftBlock::minutes($r->local_start), $r->duration_min))->all(),
            $this->timezone,
        );
    }

    public function effectiveFrom(): CarbonImmutable
    {
        return CarbonImmutable::parse(trim(explode(',', $this->effective)[0], '[('));
    }

    /** The version in force for a teacher on a date, if any. */
    public static function inForce(string $teacherId, CarbonImmutable $date): ?self
    {
        return self::query()
            ->where('teacher_id', $teacherId)
            ->where('status', 'active')
            ->whereRaw('effective @> ?::date', [$date->toDateString()])
            ->with('rules')
            ->first();
    }
}
