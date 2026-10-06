<?php

namespace Modules\Classroom\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * The provider's room for one lesson. Named "{academy}_{session}" so a
 * webhook, which carries only the room name, says which academy it is for.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $session_id
 * @property string $provider
 * @property string $name
 * @property ?CarbonImmutable $started_at
 * @property ?CarbonImmutable $finished_at
 */
final class Room extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['session_id', 'provider', 'name', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime'];
    }

    public static function nameFor(string $academyId, string $sessionId): string
    {
        return "{$academyId}_{$sessionId}";
    }

    /** @return array{0: string, 1: string}|null [academy id, session id] */
    public static function parseName(string $name): ?array
    {
        return preg_match('/^([0-9a-f-]{36})_([0-9a-f-]{36})$/', $name, $m) ? [$m[1], $m[2]] : null;
    }
}
