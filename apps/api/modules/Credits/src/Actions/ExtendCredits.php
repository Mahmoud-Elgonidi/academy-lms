<?php

namespace Modules\Credits\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Credits\Models\CreditAccount;
use Modules\Credits\Models\CreditEntry;
use Modules\Credits\Models\CreditLot;

/**
 * Pushes back the expiry of an account's lots not yet lapsed, by whole days
 * (an academy holiday gives its days back: docs/architecture/02, rule 15),
 * those with minutes set aside for lessons too. Lots without an expiry or
 * already lapsed stay as they are. One ledger entry, with the lots and the
 * days; the same key twice extends once.
 */
final class ExtendCredits
{
    /** @return int how many lots moved */
    public function handle(string $accountId, int $days, string $idempotencyKey, ?string $actorId = null, ?string $reason = null): int
    {
        if ($days <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($accountId, $days, $idempotencyKey, $actorId, $reason) {
            $account = CreditAccount::lock($accountId);
            if (CreditEntry::recorded($idempotencyKey)) {
                return 0;
            }

            $lots = CreditLot::query()->where('account_id', $accountId)
                ->whereNotNull('expires_at')->where('expires_at', '>', CarbonImmutable::now())->lockForUpdate()->get();
            foreach ($lots as $lot) {
                $lot->expires_at = $lot->expires_at->addDays($days);
                $lot->save();
            }

            $account->version++;
            $account->save();
            CreditEntry::record($account, 'extend', (int) $lots->sum('remaining_min'), $idempotencyKey, actorId: $actorId, reason: $reason, meta: ['days' => $days, 'lots' => $lots->pluck('id')->all()]);

            return $lots->count();
        });
    }
}
