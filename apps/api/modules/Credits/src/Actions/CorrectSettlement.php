<?php

namespace Modules\Credits\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Credits\Contracts\Events\CreditsGranted;
use Modules\Credits\Contracts\LotSource;
use Modules\Credits\Models\CreditAccount;
use Modules\Credits\Models\CreditEntry;
use Modules\Credits\Models\CreditLot;
use Modules\Credits\Models\CreditReservation;

/**
 * A lesson decided once and decided again (a review, a dispute): its credit
 * moves the other way with new entries, never by changing old ones
 * (docs/architecture/02, section 5).
 *
 *  - used, now not held (the teacher was absent after all): the minutes come
 *    back as a makeup lot ("restore");
 *  - turned into makeup, now held after all: the makeup lot's minutes still
 *    there are taken back ("reclaim"); what the family already booked with
 *    them stays theirs, and the shortfall is returned to the caller.
 *
 * Nothing happens when the lesson had no reservation (a free trial, an
 * unpaid lesson) or when it already stands where it should.
 */
final class CorrectSettlement
{
    /** @return int minutes that could not be taken back */
    public function handle(string $sessionId, string $effect, ?string $actorId = null, ?string $reason = null, ?int $makeupDays = null): int
    {
        return DB::transaction(function () use ($sessionId, $effect, $actorId, $reason, $makeupDays) {
            $accountId = CreditReservation::query()->where('session_id', $sessionId)->value('account_id');
            if ($accountId === null) {
                return 0;
            }
            $account = CreditAccount::lock($accountId);
            $reservation = CreditReservation::query()->where('session_id', $sessionId)->lockForUpdate()->firstOrFail();
            $now = self::settledAs($reservation);
            $want = match ($effect) {
                'consumed', 'forfeit' => 'consumed',
                'makeup' => 'makeup',
                default => $effect,
            };
            if ($now === null || $now === $want) {
                return 0;
            }
            $key = "session:{$sessionId}:correct:".CreditEntry::query()->where('session_id', $sessionId)->whereIn('type', ['restore', 'reclaim'])->count();

            if ($now === 'consumed' && $want === 'makeup') {
                $lot = CreditLot::query()->create([
                    'account_id' => $account->id,
                    'source' => LotSource::Makeup,
                    'source_id' => $sessionId,
                    'granted_min' => $reservation->minutes,
                    'remaining_min' => $reservation->minutes,
                    'expires_at' => CarbonImmutable::now()->addDays($makeupDays ?? ReleaseCredits::MAKEUP_DAYS)->endOfDay(),
                ]);
                $reservation->status = 'released';
                $reservation->save();
                $account->consumed_min -= $reservation->minutes;
                $account->available_min += $reservation->minutes;
                $account->version++;
                $account->save();
                CreditEntry::record($account, 'restore', $reservation->minutes, $key, lotId: $lot->id, sessionId: $sessionId, actorId: $actorId, reason: $reason, meta: ['from' => 'consumed', 'to' => 'makeup']);
                DB::afterCommit(fn () => event(new CreditsGranted($account->academy_id, $account->id, $lot->id, LotSource::Makeup->value, $lot->granted_min)));

                return 0;
            }

            if ($now === 'makeup' && $want === 'consumed') {
                $lot = CreditLot::query()->where('account_id', $account->id)->where('source', LotSource::Makeup->value)
                    ->where('source_id', $sessionId)->lockForUpdate()->first();
                $taken = min($reservation->minutes, (int) ($lot?->remaining_min ?? 0));
                if ($lot !== null && $taken > 0) {
                    $lot->remaining_min -= $taken;
                    $lot->save();
                }
                $reservation->status = 'consumed';
                $reservation->save();
                $account->available_min -= $taken;
                $account->consumed_min += $taken;
                $account->version++;
                $account->save();
                $short = $reservation->minutes - $taken;
                CreditEntry::record($account, 'reclaim', $taken, $key, lotId: $lot?->id, sessionId: $sessionId, actorId: $actorId, reason: $reason,
                    meta: ['from' => 'makeup', 'to' => 'consumed', 'short' => $short]);

                return $short;
            }

            throw new LogicException("A lesson's credit cannot move from {$now} to {$want}.");
        });
    }

    /** How a session's credit was settled: consumed, makeup, refund; null while held or with none. */
    public static function settledAs(?CreditReservation $reservation): ?string
    {
        return match ($reservation?->status) {
            'consumed' => 'consumed',
            'released' => CreditLot::query()->where('account_id', $reservation->account_id)->where('source', LotSource::Makeup->value)
                ->where('source_id', $reservation->session_id)->exists() ? 'makeup' : 'refund',
            default => null,
        };
    }
}
