<?php

namespace Modules\Credits\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Credits\Models\CreditAccount;
use Modules\Credits\Models\CreditEntry;
use Modules\Credits\Models\CreditReservation;

/** The session took place (or its minutes were forfeited): the reserved minutes are used. */
final class ConsumeCredits
{
    public function __construct(private readonly LowBalanceWatch $watch) {}

    public function handle(string $sessionId, string $idempotencyKey, ?string $reason = null): void
    {
        DB::transaction(function () use ($sessionId, $idempotencyKey, $reason) {
            $accountId = CreditReservation::query()->where('session_id', $sessionId)->value('account_id');
            if ($accountId === null) {
                return;
            }

            $account = CreditAccount::lock($accountId);
            $reservation = CreditReservation::heldFor($sessionId);
            if ($reservation === null || CreditEntry::recorded($idempotencyKey)) {
                return;
            }

            $before = $this->watch->measure($account);
            $reservation->status = 'consumed';
            $reservation->save();

            $account->reserved_min -= $reservation->minutes;
            $account->consumed_min += $reservation->minutes;
            $account->version++;
            $account->save();

            CreditEntry::record($account, 'consume', $reservation->minutes, $idempotencyKey, sessionId: $sessionId, reason: $reason);
            $this->watch->check($account, $before);
        });
    }
}
