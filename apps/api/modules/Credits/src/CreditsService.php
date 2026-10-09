<?php

namespace Modules\Credits;

use Carbon\CarbonImmutable;
use Modules\Credits\Actions\ConsumeCredits;
use Modules\Credits\Actions\CorrectSettlement;
use Modules\Credits\Actions\ExtendCredits;
use Modules\Credits\Actions\GrantCredits;
use Modules\Credits\Actions\OpenCreditAccount;
use Modules\Credits\Actions\ReleaseCredits;
use Modules\Credits\Actions\ReserveCredits;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Credits\Contracts\DTOs\CreditBalance;
use Modules\Credits\Contracts\LotSource;
use Modules\Credits\Contracts\ReleaseMode;
use Modules\Credits\Models\CreditAccount;
use Modules\Credits\Models\CreditLot;
use Modules\Credits\Models\CreditReservation;

final class CreditsService implements CreditsApi
{
    public function __construct(
        private readonly OpenCreditAccount $open,
        private readonly GrantCredits $grant,
        private readonly ReserveCredits $reserve,
        private readonly ConsumeCredits $consume,
        private readonly ReleaseCredits $release,
        private readonly ExtendCredits $extend,
        private readonly CorrectSettlement $correct,
    ) {}

    public function openAccount(string $studentId, string $scopeType, ?string $scopeId, ?int $lowBalanceMin = null): string
    {
        return $this->open->handle($studentId, $scopeType, $scopeId, $lowBalanceMin)->id;
    }

    public function setLowBalance(string $accountId, ?int $minutes): void
    {
        CreditAccount::query()->whereKey($accountId)->update(['low_balance_min' => $minutes]);
    }

    public function grant(string $accountId, LotSource $source, ?string $sourceId, int $minutes, ?CarbonImmutable $expiresAt, string $idempotencyKey, ?string $actorId = null, ?string $reason = null): string
    {
        return $this->grant->handle($accountId, $source, $sourceId, $minutes, $expiresAt, $idempotencyKey, $actorId, $reason)->id;
    }

    public function reserve(string $sessionId, string $accountId, int $minutes, CarbonImmutable $usableAt, string $idempotencyKey, bool $forMakeup = false): bool
    {
        return $this->reserve->handle($sessionId, $accountId, $minutes, $usableAt, $idempotencyKey, $forMakeup);
    }

    public function consume(string $sessionId, string $idempotencyKey, ?string $reason = null): void
    {
        $this->consume->handle($sessionId, $idempotencyKey, $reason);
    }

    public function release(string $sessionId, ReleaseMode $mode, string $idempotencyKey, ?string $actorId = null, ?string $reason = null, ?int $makeupDays = null): void
    {
        $this->release->handle($sessionId, $mode, $idempotencyKey, $actorId, $reason, $makeupDays);
    }

    public function extend(string $accountId, int $days, string $idempotencyKey, ?string $actorId = null, ?string $reason = null): int
    {
        return $this->extend->handle($accountId, $days, $idempotencyKey, $actorId, $reason);
    }

    public function isReserved(string $sessionId): bool
    {
        return CreditReservation::query()->where('session_id', $sessionId)->where('status', 'held')->exists();
    }

    public function settledAs(string $sessionId): ?string
    {
        return CorrectSettlement::settledAs(CreditReservation::query()->where('session_id', $sessionId)->first());
    }

    public function correct(string $sessionId, string $effect, ?string $actorId = null, ?string $reason = null, ?int $makeupDays = null): int
    {
        return $this->correct->handle($sessionId, $effect, $actorId, $reason, $makeupDays);
    }

    public function balance(string $accountId): CreditBalance
    {
        CreditAccount::query()->findOrFail($accountId);

        return $this->balances([$accountId])[$accountId];
    }

    public function balances(array $accountIds): array
    {
        $accounts = CreditAccount::query()->whereIn('id', array_values(array_unique($accountIds)))->get();
        if ($accounts->isEmpty()) {
            return [];
        }
        $ids = $accounts->modelKeys();
        // Each account's lot that lapses first, and its makeup minutes.
        $next = CreditLot::query()->whereIn('account_id', $ids)->where('remaining_min', '>', 0)->whereNotNull('expires_at')
            ->orderBy('account_id')->orderBy('expires_at')->get(['account_id', 'expires_at', 'remaining_min'])
            ->unique('account_id')->keyBy('account_id');
        $makeup = CreditLot::query()->whereIn('account_id', $ids)->where('source', LotSource::Makeup->value)
            ->groupBy('account_id')->selectRaw('account_id, sum(remaining_min) as minutes')->pluck('minutes', 'account_id');

        $balances = [];
        foreach ($accounts as $account) {
            $lot = $next->get($account->id);
            $balances[$account->id] = new CreditBalance(
                accountId: $account->id,
                availableMin: $account->available_min,
                reservedMin: $account->reserved_min,
                consumedMin: $account->consumed_min,
                nextExpiry: $lot?->expires_at,
                expiringMin: $lot?->remaining_min ?? 0,
                makeupMin: (int) ($makeup[$account->id] ?? 0),
            );
        }

        return $balances;
    }
}
