<?php

namespace Modules\Credits\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * The minutes set aside for one session, and which lots they came from.
 *
 * @property string $session_id
 * @property string $account_id
 * @property int $minutes
 * @property list<array{lot_id: string, minutes: int}> $allocations
 * @property string $status held | consumed | released
 */
final class CreditReservation extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $primaryKey = 'session_id';

    protected $keyType = 'string';

    protected $fillable = ['session_id', 'account_id', 'minutes', 'allocations', 'status'];

    protected function casts(): array
    {
        return ['minutes' => 'integer', 'allocations' => 'array'];
    }

    public static function heldFor(string $sessionId): ?self
    {
        return self::query()->where('session_id', $sessionId)->where('status', 'held')->lockForUpdate()->first();
    }

    /** The key is (academy_id, session_id); RLS supplies the academy. */
    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('session_id', $this->getAttribute('session_id'));
    }
}
