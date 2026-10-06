<?php

namespace Modules\Crm\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One thing on a lead's timeline: a call, a message, a note, or what the
 * system did (a trial booked, attended, assessed; the stage it moved to).
 *
 * @property string $id
 * @property string $lead_id
 * @property string $kind
 * @property ?string $body
 * @property array<string, mixed> $data
 * @property ?string $actor_id
 * @property CarbonImmutable $created_at
 */
final class LeadActivity extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const CONTACT_KINDS = ['call', 'whatsapp', 'note'];

    public const UPDATED_AT = null;

    protected $fillable = ['lead_id', 'kind', 'body', 'data', 'actor_id'];

    protected $attributes = ['data' => '{}'];

    protected function casts(): array
    {
        return ['data' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** @param  array<string, mixed>  $data */
    public static function record(Lead $lead, string $kind, ?string $body, array $data, ?string $actorId): self
    {
        return self::query()->create(['lead_id' => $lead->id, 'kind' => $kind, 'body' => $body, 'data' => $data, 'actor_id' => $actorId]);
    }
}
