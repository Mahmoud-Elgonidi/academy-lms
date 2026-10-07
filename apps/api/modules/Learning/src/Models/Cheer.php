<?php

namespace Modules\Learning\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A guardian's encouragement to a student ("برافو!"), shown to the student
 * once with a little celebration.
 *
 * @property string $id
 * @property string $student_id
 * @property string $from_id
 * @property string $kind
 * @property CarbonImmutable $created_at
 * @property ?CarbonImmutable $seen_at
 */
final class Cheer extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const KINDS = ['clap', 'star', 'heart', 'fire'];

    public $timestamps = false;

    protected $fillable = ['student_id', 'from_id', 'kind', 'created_at', 'seen_at'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'seen_at' => 'immutable_datetime'];
    }
}
