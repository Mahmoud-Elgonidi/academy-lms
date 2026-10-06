<?php

namespace Modules\Scheduling\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $schedule_id
 * @property string $teacher_id
 * @property int $weekday
 * @property string $local_start
 * @property int $duration_min
 */
final class ShiftRule extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'teacher_shift_rules';

    protected $primaryKey = null;

    protected $fillable = ['schedule_id', 'teacher_id', 'weekday', 'local_start', 'duration_min', 'kind'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'duration_min' => 'integer'];
    }
}
