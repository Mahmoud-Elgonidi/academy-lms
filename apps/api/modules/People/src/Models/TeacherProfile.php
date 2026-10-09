<?php

namespace Modules\People\Models;

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
 * @property string $status
 */
final class TeacherProfile extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'gender', 'languages', 'dialect', 'accepts_new', 'accepts_trials', 'max_weekly_min', 'max_daily_lessons', 'age_groups', 'status'];

    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'accepts_new' => 'boolean',
            'accepts_trials' => 'boolean',
            'max_weekly_min' => 'integer',
            'max_daily_lessons' => 'integer',
            'age_groups' => 'array',
        ];
    }

    /** The key is (academy_id, user_id); RLS supplies the academy. */
    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('user_id', $this->getAttribute('user_id'));
    }
}
