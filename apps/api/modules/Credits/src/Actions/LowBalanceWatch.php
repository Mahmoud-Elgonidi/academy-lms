<?php

namespace Modules\Credits\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Credits\Contracts\Events\CreditBalanceLow;
use Modules\Credits\Contracts\LotSource;
use Modules\Credits\Models\CreditAccount;
use Modules\Credits\Models\CreditLot;

/**
 * Raises CreditBalanceLow once, when the paid-for minutes left (free plus
 * booked, makeup minutes aside: they are owed lessons, not a balance to
 * renew) cross the account's mark on the way down.
 */
final class LowBalanceWatch
{
    public function measure(CreditAccount $account): int
    {
        $makeup = (int) CreditLot::query()->where('account_id', $account->id)->where('source', LotSource::Makeup->value)->sum('remaining_min');

        return $account->remainingMin() - $makeup;
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
