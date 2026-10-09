<?php

namespace Modules\Safeguarding\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One step on a concern: a lead's note (encrypted) or an event (taken,
 * escalated, a teacher paused or let back, closed, reopened). Written once.
 *
 * @property string $id
 * @property string $concern_id
 * @property ?string $author_id
 * @property string $kind note | event
 * @property ?string $event
 * @property ?string $body
 * @property array<string, mixed> $data
 * @property CarbonImmutable $created_at
 */
final class ConcernEntry extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['concern_id', 'author_id', 'kind', 'event', 'body', 'data'];

    protected $attributes = ['data' => '{}'];

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'data' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** @param  array<string, mixed>  $data */
    public static function event(Concern $concern, string $event, ?string $authorId, array $data = []): self
    {
        return self::query()->create(['concern_id' => $concern->id, 'author_id' => $authorId, 'kind' => 'event', 'event' => $event, 'data' => $data]);
    }
}
