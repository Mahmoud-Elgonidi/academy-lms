<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One stretch of a person in the classroom.
 *
 * @property string $id
 * @property string $session_id
 * @property string $user_id
 * @property string $role teacher | student
 * @property CarbonImmutable $joined_at
 * @property ?CarbonImmutable $left_at
 * @property string $source provider | reconcile | external_link | manual
 */
final class AttendanceSegment extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['session_id', 'user_id', 'role', 'joined_at', 'left_at', 'source'];

    protected function casts(): array
    {
        return ['joined_at' => 'immutable_datetime', 'left_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
