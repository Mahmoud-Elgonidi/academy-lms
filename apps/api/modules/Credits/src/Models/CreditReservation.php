<?php

namespace Modules\Credits\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * The minutes set aside for one session from one account, and which lots
 * they came from. A one-to-one lesson has one; a group lesson one for each
 * student in it, each from their own account.
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

    public static function heldFor(string $sessionId, string $accountId): ?self
    {
        return self::query()->where('session_id', $sessionId)->where('account_id', $accountId)->where('status', 'held')->lockForUpdate()->first();
    }

    /** @return Builder<self> the session's reservation from this account, or its one reservation when none is named */
    public static function of(string $sessionId, ?string $accountId): Builder
    {
        return self::query()->where('session_id', $sessionId)->when($accountId !== null, fn ($q) => $q->where('account_id', $accountId));
    }

    /**
     * The account a session's credit is held on: the one named, or the
     * session's only one (a one-to-one lesson). Null without a reservation.
     * A group lesson must name the account: asking it for "the" account is a mistake.
     */
    public static function accountFor(string $sessionId, ?string $accountId): ?string
    {
        if ($accountId !== null) {
            return self::of($sessionId, $accountId)->value('account_id');
        }
        $accounts = self::query()->where('session_id', $sessionId)->limit(2)->pluck('account_id');
        if ($accounts->count() > 1) {
            throw new LogicException("Session {$sessionId} holds credit for several students: name the account.");
        }

        return $accounts->first();
    }

    /** The key is (academy_id, session_id, account_id); RLS supplies the academy. */
    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('session_id', $this->getAttribute('session_id'))->where('account_id', $this->getAttribute('account_id'));
    }
}
