<?php

namespace Modules\Credits\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One line of the ledger. Append-only: the database refuses UPDATE and
 * DELETE for the app role; a correction is a new entry.
 *
 * @property string $id
 * @property string $account_id
 * @property ?string $lot_id
 * @property ?string $session_id
 * @property string $type grant | reserve | release | consume | expire
 * @property int $minutes
 * @property int $available_after
 * @property int $reserved_after
 * @property string $idempotency_key
 * @property ?string $actor_id
 * @property ?string $reason
 * @property array<string, mixed> $meta
 * @property CarbonImmutable $occurred_at
 */
final class CreditEntry extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'account_id', 'lot_id', 'session_id', 'type', 'minutes', 'available_after', 'reserved_after',
        'idempotency_key', 'actor_id', 'reason', 'meta', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'minutes' => 'integer',
            'available_after' => 'integer',
            'reserved_after' => 'integer',
            'meta' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public static function recorded(string $idempotencyKey): bool
    {
        return self::query()->where('idempotency_key', $idempotencyKey)->exists();
    }

    /**
     * Writes the entry with the account's totals after the change; the
     * caller has already changed and saved the account under its lock.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function record(CreditAccount $account, string $type, int $minutes, string $idempotencyKey, ?string $lotId = null, ?string $sessionId = null, ?string $actorId = null, ?string $reason = null, array $meta = []): self
    {
        if ($minutes < 0) {
            throw new LogicException('Ledger minutes are never negative; the type says which way they move.');
        }

        return self::query()->create([
            'account_id' => $account->id,
            'lot_id' => $lotId,
            'session_id' => $sessionId,
            'type' => $type,
            'minutes' => $minutes,
            'available_after' => $account->available_min,
            'reserved_after' => $account->reserved_min,
            'idempotency_key' => $idempotencyKey,
            'actor_id' => $actorId,
            'reason' => $reason,
            'meta' => $meta,
            'occurred_at' => CarbonImmutable::now(),
        ]);
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Credit entries are append-only.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Credit entries are append-only.');
    }
}
