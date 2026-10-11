<?php

namespace Modules\Games\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A guardian's choice for their child: games on or off, and minutes a day
 * (null: the academy's), and no play at night. Changed only through
 * SaveGameLimit.
 *
 * @property string $academy_id
 * @property string $student_id
 * @property bool $enabled
 * @property ?int $daily_minutes
 * @property bool $night_lock no play from 21:00 to 07:00 on the learner's clock
 * @property string $updated_by
 */
final class GameLimit extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $table = 'game_limits';

    protected $primaryKey = 'student_id';

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'daily_minutes' => 'integer', 'night_lock' => 'boolean'];
    }
}
