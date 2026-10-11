<?php

namespace Modules\Games\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * The academy's choice: games on or off for its learners, and the minutes
 * a learner may play a day (null: no limit). One row, made on first read.
 *
 * @property string $academy_id
 * @property bool $enabled
 * @property ?int $daily_minutes
 * @property ?string $updated_by
 */
final class GameSettings extends Model
{
    use BelongsToTenant;

    public const DEFAULT_MINUTES = 30;

    public $incrementing = false;

    protected $table = 'game_settings';

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $guarded = [];

    protected $attributes = ['enabled' => true, 'daily_minutes' => self::DEFAULT_MINUTES];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'daily_minutes' => 'integer'];
    }

    public static function current(): self
    {
        return self::query()->first() ?? new self;
    }
}
