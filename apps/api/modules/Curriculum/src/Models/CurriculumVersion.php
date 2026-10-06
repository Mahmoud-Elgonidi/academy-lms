<?php

namespace Modules\Curriculum\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $id
 * @property string $program_id
 * @property int $version
 * @property string $status draft | published | retired
 * @property ?CarbonImmutable $published_at
 */
final class CurriculumVersion extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['program_id', 'version', 'status', 'published_at', 'published_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'published_at' => 'immutable_datetime'];
    }

    /** @return HasMany<Node, $this> */
    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class, 'version_id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
