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
 * they came from. Makeup: they become a makeup lot, valid for the
 * academy's makeup days (MAKEUP_DAYS by default) and never refunded
 * (docs/architecture/02, section 3).
 */
final class ReleaseCredits
{
    public const MAKEUP_DAYS = 30;

    public function __construct(private readonly LowBalanceWatch $watch) {}

    public function handle(string $sessionId, ReleaseMode $mode, string $idempotencyKey, ?string $actorId = null, ?string $reason = null, ?int $makeupDays = null, ?string $accountId = null): void
    {
        DB::transaction(function () use ($sessionId, $mode, $idempotencyKey, $actorId, $reason, $makeupDays, $accountId) {
            $accountId = CreditReservation::accountFor($sessionId, $accountId);
            if ($accountId === null) {
                return;
            }

            $account = CreditAccount::lock($accountId);
            $reservation = CreditReservation::heldFor($sessionId, $accountId);
            if ($reservation === null || CreditEntry::recorded($idempotencyKey)) {
                return;
            }

            // Turned into makeup credit, a paid lesson's minutes stop counting towards renewal: it may be due now.
            $before = $this->watch->measure($account);
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
                    'expires_at' => CarbonImmutable::now()->addDays($makeupDays ?? self::MAKEUP_DAYS)->endOfDay(),
                ]);
            }

            $account->reserved_min -= $reservation->minutes;
            $account->available_min += $reservation->minutes;
            $account->version++;
            $account->save();

            CreditEntry::record($account, 'release', $reservation->minutes, $idempotencyKey, lotId: $lot?->id, sessionId: $sessionId, actorId: $actorId, reason: $reason, meta: ['mode' => $mode->value]);
            $this->watch->check($account, $before);

            if ($lot !== null) {
                DB::afterCommit(fn () => event(new CreditsGranted($account->academy_id, $account->id, $lot->id, LotSource::Makeup->value, $lot->granted_min)));
            }
        });
    }
}
