<?php

namespace Modules\Credits\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Credits\Contracts\LotSource;
use Modules\Credits\Domain\LotBalance;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * @property string $id
 * @property string $account_id
 * @property LotSource $source
 * @property ?string $source_id
 * @property int $granted_min
 * @property int $remaining_min
 * @property ?CarbonImmutable $expires_at
 * @property CarbonImmutable $created_at
 */
final class CreditLot extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['account_id', 'source', 'source_id', 'granted_min', 'remaining_min', 'expires_at'];

    protected function casts(): array
    {
        return [
            'source' => LotSource::class,
            'granted_min' => 'integer',
            'remaining_min' => 'integer',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function toBalance(): LotBalance
    {
        return new LotBalance($this->id, $this->source->value, $this->remaining_min, $this->expires_at, $this->created_at);
    }

    /**
     * Makeup minutes each account still owes as lessons: those not booked yet
     * and those set aside for a makeup lesson (which draws on makeup lots only,
     * so its first lot says which it is). Renewal leaves them all aside.
     *
     * @param  list<string>  $accountIds
     * @return array<string, int> by account id; an account with none is left out
     */
    public static function makeupOwed(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }
        $free = self::query()->whereIn('account_id', $accountIds)->where('source', LotSource::Makeup->value)
            ->groupBy('account_id')->selectRaw('account_id, sum(remaining_min) as minutes')->pluck('minutes', 'account_id');
        $booked = CreditReservation::query()->whereIn('account_id', $accountIds)->where('status', 'held')
            ->whereExists(fn ($q) => $q->from('credit_lots as l')->whereColumn('l.academy_id', 'credit_reservations.academy_id')
                ->whereRaw("l.id = (credit_reservations.allocations->0->>'lot_id')::uuid")->where('l.source', LotSource::Makeup->value))
            ->groupBy('account_id')->selectRaw('account_id, sum(minutes) as minutes')->pluck('minutes', 'account_id');

        $owed = [];
        foreach ([$free, $booked] as $minutes) {
            foreach ($minutes as $accountId => $sum) {
                $owed[(string) $accountId] = ($owed[(string) $accountId] ?? 0) + (int) $sum;
            }
        }

        return $owed;
    }
}
