<?php

namespace Modules\Learning\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A reward a student collected for a challenge in one period (once: the
 * database holds it). Its points add to the student's journey points.
 *
 * @property string $id
 * @property string $student_id
 * @property string $challenge
 * @property string $period
 * @property int $points
 * @property ?string $sticker
 * @property CarbonImmutable $claimed_at
 */
final class ChallengeClaim extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['student_id', 'challenge', 'period', 'points', 'sticker', 'claimed_at'];

    protected function casts(): array
    {
        return ['points' => 'integer', 'claimed_at' => 'immutable_datetime'];
    }
}
