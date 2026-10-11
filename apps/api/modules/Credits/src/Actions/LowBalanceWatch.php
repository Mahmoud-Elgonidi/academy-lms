<?php

namespace Modules\Credits\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Credits\Contracts\Events\CreditBalanceLow;
use Modules\Credits\Models\CreditAccount;
use Modules\Credits\Models\CreditLot;

/**
 * Raises CreditBalanceLow once, when the paid-for minutes left (free plus
 * booked, makeup minutes aside, booked or not: they are owed lessons, not a
 * balance to renew) cross the account's mark on the way down. Every write
 * that can lower them measures before and checks after: a lesson used, a
 * lesson turned into makeup credit, a lot lapsing.
 */
final class LowBalanceWatch
{
    public function measure(CreditAccount $account): int
    {
        return $account->remainingMin() - (CreditLot::makeupOwed([$account->id])[$account->id] ?? 0);
    }

    public function check(CreditAccount $account, int $before): void
    {
        $mark = $account->low_balance_min;
        $now = $this->measure($account);

        if ($mark !== null && $before > $mark && $now <= $mark) {
            DB::afterCommit(fn () => event(new CreditBalanceLow($account->academy_id, $account->id, $account->student_id, $now)));
        }
    }
}
