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
 * Sets minutes aside for one session from one account, from lots still
 * valid when it starts (makeup lots only for a makeup session, and of those
 * only the ones kept for that kind of makeup lesson: private or group). All
 * or nothing; a session already reserved from this account stays as it is.
 * A group lesson holds one reservation for each student's account.
 */
final class ReserveCredits
{
    public function handle(string $sessionId, string $accountId, int $minutes, CarbonImmutable $usableAt, string $idempotencyKey, bool $forMakeup = false, string $makeupFor = LotAllocator::PRIVATE): bool
    {
        return DB::transaction(function () use ($sessionId, $accountId, $minutes, $usableAt, $idempotencyKey, $forMakeup, $makeupFor) {
            $account = CreditAccount::lock($accountId);
            if (CreditEntry::recorded($idempotencyKey)) {
                return CreditReservation::of($sessionId, $accountId)->where('status', 'held')->exists();
            }
            $existing = CreditReservation::of($sessionId, $accountId)->lockForUpdate()->first();
            if ($existing !== null) {
                // A student back in a group lesson they had left (a pause over): their refunded seat is reserved
                // afresh under a new key; the ledger keeps both. Anything else stays as it is.
                if ($existing->status !== 'released' || CorrectSettlement::settledAs($existing) !== 'refund') {
                    return $existing->status === 'held';
                }
                $existing->delete();
            }

            $lots = CreditLot::query()->where('account_id', $account->id)->where('remaining_min', '>', 0)->lockForUpdate()->get()->keyBy('id');
            $allocation = LotAllocator::allocate($lots->map(fn (CreditLot $l) => $l->toBalance())->values()->all(), $minutes, $usableAt, $forMakeup, $makeupFor);
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
