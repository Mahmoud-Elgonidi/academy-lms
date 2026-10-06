<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A tentative booking while a coordinator finishes enrolling. Counts as busy
 * for everyone else until it expires.
 *
 * @property string $id
 * @property string $batch_id
 * @property string $teacher_id
 * @property ?string $student_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable $expires_at
 * @property string $created_by
 */
final class BookingHold extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const UPDATED_AT = null;

    public const MINUTES = 10;

    protected $fillable = ['batch_id', 'teacher_id', 'student_id', 'starts_at', 'ends_at', 'expires_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
