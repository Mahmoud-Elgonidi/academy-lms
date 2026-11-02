<?php

namespace Modules\Credits\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Credits\Contracts\Events\CreditsExpiringSoon;
use Modules\Credits\Contracts\LotSource;
use Modules\Credits\Models\CreditAccount;
use Modules\Credits\Models\CreditEntry;
use Modules\Credits\Models\CreditLot;

/**
 * Minutes left in lots past their date lapse (one ledger entry per lot); and,
 * CreditsApi::EXPIRY_NOTICE_DAYS before, a purchased lot's coming lapse is
 * told once for its date (CreditsExpiringSoon, after the commit; a date moved
 * later is told again for the new one).
 */
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

    /** @return int how many lots' coming lapse was told */
    public function notice(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $soon = CreditLot::query()->where('source', LotSource::Purchase->value)->where('remaining_min', '>', 0)
            ->where('expires_at', '>', $now)->where('expires_at', '<=', $now->addDays(CreditsApi::EXPIRY_NOTICE_DAYS))
            ->where(fn ($q) => $q->whereNull('expiry_noticed_for')->orWhereColumn('expiry_noticed_for', '<>', 'expires_at'))
            ->orderBy('expires_at')->get();

        $told = 0;
        foreach ($soon as $lot) {
            $told += DB::transaction(function () use ($lot) {
                // Claimed under the lot's lock, so two runs never tell it twice.
                $claimed = CreditLot::query()->whereKey($lot->id)->where('expires_at', $lot->expires_at)
                    ->where(fn ($q) => $q->whereNull('expiry_noticed_for')->orWhereColumn('expiry_noticed_for', '<>', 'expires_at'))
                    ->update(['expiry_noticed_for' => DB::raw('expires_at')]);
                if ($claimed === 0) {
                    return 0;
                }
                $event = new CreditsExpiringSoon((string) $lot->academy_id, $lot->account_id, $lot->id, $lot->remaining_min, $lot->expires_at);
                DB::afterCommit(fn () => event($event));

                return 1;
            });
        }

        return $told;
    }
}
