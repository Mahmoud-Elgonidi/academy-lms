<?php

namespace Modules\Learning\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Report and homework settings of the current academy; defaults when it has no row.
 *
 * @property int $report_due_hours after the lesson ends
 * @property int $homework_days default time to hand homework in
 */
final class LearningSettings extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = ['report_due_hours', 'homework_days'];

    protected $attributes = ['report_due_hours' => 12, 'homework_days' => 3];

    protected function casts(): array
    {
        return ['report_due_hours' => 'integer', 'homework_days' => 'integer'];
    }

    public static function current(): self
    {
        return self::query()->first() ?? new self;
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('academy_id', $this->getAttribute('academy_id'));
    }
}
