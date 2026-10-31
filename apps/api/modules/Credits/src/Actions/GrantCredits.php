<?php

namespace Modules\Credits\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Credits\Contracts\Events\CreditsGranted;
use Modules\Credits\Contracts\LotSource;
use Modules\Credits\Domain\LotAllocator;
use Modules\Credits\Models\CreditAccount;
use Modules\Credits\Models\CreditEntry;
use Modules\Credits\Models\CreditLot;

/**
 * Adds a lot of minutes to an account: a paid block, a makeup, a bonus. A
 * makeup lot may be kept for one kind of makeup lesson ($useFor: private,
 * with the course's teacher; group, a free seat in another group's lesson).
 */
final class GrantCredits
{
    public function handle(string $accountId, LotSource $source, ?string $sourceId, int $minutes, ?CarbonImmutable $expiresAt, string $idempotencyKey, ?string $actorId = null, ?string $reason = null, ?string $useFor = null): CreditLot
    {
        if ($minutes <= 0) {
            throw new InvalidArgumentException('A grant is a positive number of minutes.');
        }
        if ($useFor !== null && ($source !== LotSource::Makeup || ! in_array($useFor, [LotAllocator::PRIVATE, LotAllocator::GROUP], true))) {
            throw new InvalidArgumentException('Only a makeup lot is kept for private or group lessons.');
        }

        return DB::transaction(function () use ($accountId, $source, $sourceId, $minutes, $expiresAt, $idempotencyKey, $actorId, $reason, $useFor) {
            $account = CreditAccount::lock($accountId);
            $done = CreditEntry::query()->where('idempotency_key', $idempotencyKey)->value('lot_id');
            if ($done !== null) {
                return CreditLot::query()->findOrFail($done);
            }

            $lot = CreditLot::query()->create([
                'account_id' => $account->id,
                'source' => $source,
                'source_id' => $sourceId,
                'granted_min' => $minutes,
                'remaining_min' => $minutes,
                'expires_at' => $expiresAt?->utc(),
                'use_for' => $useFor,
            ]);

            $account->available_min += $minutes;
            $account->version++;
            $account->save();

            CreditEntry::record($account, 'grant', $minutes, $idempotencyKey, lotId: $lot->id, actorId: $actorId, reason: $reason ?? $source->value, meta: array_filter([
                'source' => $source->value,
                'source_id' => $sourceId,
                'expires_at' => $expiresAt?->utc()->toIso8601String(),
                'use_for' => $useFor,
            ]));

            DB::afterCommit(fn () => event(new CreditsGranted($account->academy_id, $account->id, $lot->id, $source->value, $minutes)));

            return $lot;
        });
    }
}
