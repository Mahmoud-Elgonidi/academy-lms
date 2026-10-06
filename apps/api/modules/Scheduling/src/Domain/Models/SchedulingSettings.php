<?php

namespace Modules\Scheduling\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * Scheduling settings of the current academy; defaults when it has no row.
 *
 * @property string $shift_mode teacher_proposes | admin_only
 * @property int $min_block_min
 * @property int $reduction_notice_days
 * @property int $proposal_reply_hours
 */
final class SchedulingSettings extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $primaryKey = 'academy_id';

    protected $keyType = 'string';

    protected $fillable = ['shift_mode', 'min_block_min', 'reduction_notice_days', 'proposal_reply_hours'];

    protected $attributes = [
        'shift_mode' => 'teacher_proposes',
        'min_block_min' => 60,
        'reduction_notice_days' => 14,
        'proposal_reply_hours' => 48,
    ];

    public static function current(): self
    {
        return self::query()->first() ?? new self;
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('academy_id', $this->getAttribute('academy_id'));
    }
}
