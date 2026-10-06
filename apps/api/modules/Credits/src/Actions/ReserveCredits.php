<?php

namespace Modules\Credits\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Credits\Domain\LotAllocator;
use Modules\Credits\Models\CreditAccount;
use Modules\Credits\Models\CreditEntry;
use Modules\Credits\Models\CreditLot;
use Modules\Credits\Models\CreditReservation;

/**
 * Sets minutes aside for one session, from lots still valid when it starts
 * (makeup lots only for a makeup session). All or nothing; a session
 * already reserved stays as it is.
 */
final class ReserveCredits
{
    public function handle(string $sessionId, string $accountId, int $minutes, CarbonImmutable $usableAt, string $idempotencyKey, bool $forMakeup = false): bool
    {
        return DB::transaction(function () use ($sessionId, $accountId, $minutes, $usableAt, $idempotencyKey, $forMakeup) {
            $account = CreditAccount::lock($accountId);
            if (CreditReservation::query()->where('session_id', $sessionId)->exists() || CreditEntry::recorded($idempotencyKey)) {
                return CreditReservation::query()->where('session_id', $sessionId)->where('status', 'held')->exists();
            }

            $lots = CreditLot::query()->where('account_id', $account->id)->where('remaining_min', '>', 0)->lockForUpdate()->get()->keyBy('id');
            $allocation = LotAllocator::allocate($lots->map(fn (CreditLot $l) => $l->toBalance())->values()->all(), $minutes, $usableAt, $forMakeup);
            if ($allocation === null) {
                return false;
            }

            foreach ($allocation as $lotId => $taken) {
                $lots[$lotId]->decrement('remaining_min', $taken);
            }

            CreditReservation::query()->create([
                'session_id' => $sessionId,
                'account_id' => $account->id,
                'minutes' => $minutes,
                'allocations' => array_map(fn (string $lotId, int $taken) => ['lot_id' => $lotId, 'minutes' => $taken], array_keys($allocation), $allocation),
                'status' => 'held',
            ]);

            $account->available_min -= $minutes;
            $account->reserved_min += $minutes;
            $account->version++;
            $account->save();

            CreditEntry::record($account, 'reserve', $minutes, $idempotencyKey, sessionId: $sessionId, meta: ['lots' => $allocation]);

            return true;
        });
    }
}
