<?php

namespace Modules\Scheduling\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $proposal_id
 * @property int $revision
 * @property string $author_role
 * @property int $weekday
 * @property string $local_start
 * @property int $duration_min
 */
final class ShiftProposalRule extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['proposal_id', 'revision', 'author_role', 'weekday', 'local_start', 'duration_min'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'duration_min' => 'integer', 'revision' => 'integer'];
    }
}
