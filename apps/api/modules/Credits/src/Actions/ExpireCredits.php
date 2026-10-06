<?php

namespace Modules\Credits\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Credits\Models\CreditAccount;
use Modules\Credits\Models\CreditEntry;
use Modules\Credits\Models\CreditLot;

/** Minutes left in lots past their date lapse (one ledger entry per lot). */
final class ExpireCredits
{
    public function __construct(private readonly LowBalanceWatch $watch) {}

    /** @return int how many lots lapsed */
    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $due = CreditLot::query()->where('remaining_min', '>', 0)->where('expires_at', '<=', $now)->pluck('account_id', 'id');

        foreach ($due as $lotId => $accountId) {
            DB::transaction(function () use ($lotId, $accountId) {
                $account = CreditAccount::lock($accountId);
                $lot = CreditLot::query()->whereKey($lotId)->lockForUpdate()->first();
                $key = "lot:{$lotId}:expire:".($lot?->remaining_min ?? 0).':'.$account->version;
                if ($lot === null || $lot->remaining_min === 0) {
                    return;
                }

                $before = $this->watch->measure($account);
                $minutes = $lot->remaining_min;
                $lot->remaining_min = 0;
                $lot->save();

                $account->available_min -= $minutes;
                $account->version++;
                $account->save();

                CreditEntry::record($account, 'expire', $minutes, $key, lotId: $lot->id, reason: 'expired', meta: ['source' => $lot->source->value]);
                $this->watch->check($account, $before);
            });
        }

        return $due->count();
    }
}
