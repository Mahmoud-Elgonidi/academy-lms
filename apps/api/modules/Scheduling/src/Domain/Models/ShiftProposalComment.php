<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $id
 * @property string $proposal_id
 * @property ?string $author_id
 * @property string $author_role teacher | admin | system
 * @property string $body
 * @property CarbonImmutable $created_at
 */
final class ShiftProposalComment extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['proposal_id', 'author_id', 'author_role', 'body'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
