<?php

namespace Modules\Credits\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Credits\Contracts\Events\CreditsGranted;
use Modules\Credits\Contracts\LotSource;
use Modules\Credits\Contracts\ReleaseMode;
use Modules\Credits\Models\CreditAccount;
use Modules\Credits\Models\CreditEntry;
use Modules\Credits\Models\CreditLot;
use Modules\Credits\Models\CreditReservation;

/**
 * The session did not take place. Refund: the minutes go back to the lots
 * they came from. Makeup: they become a makeup lot, valid for
 * MAKEUP_DAYS and never refunded (docs/architecture/02, section 3).
 */
final class ReleaseCredits
{
    public const MAKEUP_DAYS = 30;

    public function handle(string $sessionId, ReleaseMode $mode, string $idempotencyKey, ?string $actorId = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($sessionId, $mode, $idempotencyKey, $actorId, $reason) {
            $accountId = CreditReservation::query()->where('session_id', $sessionId)->value('account_id');
            if ($accountId === null) {
                return;
            }

            $account = CreditAccount::lock($accountId);
            $reservation = CreditReservation::heldFor($sessionId);
            if ($reservation === null || CreditEntry::recorded($idempotencyKey)) {
                return;
            }

            $reservation->status = 'released';
            $reservation->save();

            $lot = null;
            if ($mode === ReleaseMode::Refund) {
                foreach ($reservation->allocations as $allocation) {
                    CreditLot::query()->whereKey($allocation['lot_id'])->increment('remaining_min', $allocation['minutes']);
                }
            } else {
                $lot = CreditLot::query()->create([
                    'account_id' => $account->id,
                    'source' => LotSource::Makeup,
                    'source_id' => $sessionId,
                    'granted_min' => $reservation->minutes,
                    'remaining_min' => $reservation->minutes,
                    'expires_at' => CarbonImmutable::now()->addDays(self::MAKEUP_DAYS),
                ]);
            }

            $account->reserved_min -= $reservation->minutes;
            $account->available_min += $reservation->minutes;
            $account->version++;
            $account->save();

            CreditEntry::record($account, 'release', $reservation->minutes, $idempotencyKey, lotId: $lot?->id, sessionId: $sessionId, actorId: $actorId, reason: $reason, meta: ['mode' => $mode->value]);

            if ($lot !== null) {
                DB::afterCommit(fn () => event(new CreditsGranted($account->academy_id, $account->id, $lot->id, LotSource::Makeup->value, $lot->granted_min)));
            }
        });
    }
}
