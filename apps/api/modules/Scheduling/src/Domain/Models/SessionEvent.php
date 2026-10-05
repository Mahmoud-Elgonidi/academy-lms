<?php

namespace Modules\Scheduling\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $id
 * @property string $session_id
 * @property SessionEventKind $kind
 * @property ?string $actor_id
 * @property array<string, mixed> $data
 * @property CarbonImmutable $occurred_at
 */
final class SessionEvent extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $table = 'session_events';

    protected $fillable = ['session_id', 'kind', 'actor_id', 'data', 'occurred_at'];

    protected $attributes = ['data' => '{}'];

    protected function casts(): array
    {
        return [
            'kind' => SessionEventKind::class,
            'data' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public static function record(Session $session, SessionEventKind $kind, array $data = [], ?string $actorId = null, ?CarbonImmutable $at = null): self
    {
        return self::query()->create([
            'session_id' => $session->id,
            'kind' => $kind,
            'actor_id' => $actorId,
            'data' => $data,
            'occurred_at' => $at ?? CarbonImmutable::now(),
        ]);
    }
}
