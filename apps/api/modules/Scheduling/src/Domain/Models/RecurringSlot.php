<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Scheduling\Domain\Slots\WeeklyTime;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * An enrollment's weekly time on the teacher's clock. Sessions are generated
 * from it over the horizon; minute_of_week and effective back the exclusion
 * constraint that keeps one teacher's weekly times apart.
 *
 * @property string $id
 * @property string $enrollment_id
 * @property string $teacher_id
 * @property string $student_id
 * @property int $weekday
 * @property string $local_start
 * @property int $duration_min
 * @property string $anchor_tz
 * @property string $status active | ended
 * @property string $effective daterange literal
 */
final class RecurringSlot extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['enrollment_id', 'teacher_id', 'student_id', 'weekday', 'local_start', 'duration_min', 'anchor_tz', 'status', 'minute_of_week', 'effective', 'created_by'];

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'duration_min' => 'integer'];
    }

    public function weeklyTime(): WeeklyTime
    {
        return WeeklyTime::at($this->weekday, substr($this->local_start, 0, 5), $this->duration_min);
    }

    /** First local date of the slot (the lower bound of "effective"). */
    public function startsOn(): CarbonImmutable
    {
        preg_match('/^[\[(](\d{4}-\d{2}-\d{2})?/', $this->effective, $m);

        return CarbonImmutable::parse($m[1] ?? '1970-01-01');
    }

    /** Last local date included, or null when open-ended. */
    public function endsOn(): ?CarbonImmutable
    {
        preg_match('/,(\d{4}-\d{2}-\d{2})?[\])]$/', $this->effective, $m);

        return isset($m[1]) ? CarbonImmutable::parse($m[1])->subDay() : null;
    }
}
