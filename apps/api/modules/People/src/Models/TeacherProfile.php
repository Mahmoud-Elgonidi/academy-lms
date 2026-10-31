<?php

namespace Modules\People\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $academy_id
 * @property string $user_id
 * @property string $gender
 * @property list<string> $languages
 * @property ?string $dialect
 * @property bool $accepts_new
 * @property bool $accepts_trials
 * @property ?int $max_weekly_min
 * @property list<string> $age_groups
 * @property ?int $max_daily_lessons
 * @property bool $accepts_groups takes group lessons
 * @property ?int $max_group_size the most students in one of their group lessons (2–20)
 * @property string $status
 * @property ?CarbonImmutable $minors_paused_at kept from new lessons with minors while a concern is looked into
 * @property ?string $minors_paused_by
 * @property ?string $minors_pause_reason
 */
final class TeacherProfile extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'gender', 'languages', 'dialect', 'accepts_new', 'accepts_trials', 'max_weekly_min', 'max_daily_lessons', 'age_groups', 'accepts_groups', 'max_group_size', 'status', 'minors_paused_at', 'minors_paused_by', 'minors_pause_reason'];

    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'accepts_new' => 'boolean',
            'accepts_trials' => 'boolean',
            'max_weekly_min' => 'integer',
            'max_daily_lessons' => 'integer',
            'accepts_groups' => 'boolean',
            'max_group_size' => 'integer',
            'age_groups' => 'array',
            'minors_paused_at' => 'immutable_datetime',
        ];
    }

    /** The key is (academy_id, user_id); RLS supplies the academy. */
    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('user_id', $this->getAttribute('user_id'));
    }
}
