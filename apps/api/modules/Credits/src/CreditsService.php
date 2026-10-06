<?php

namespace Modules\Credits;

use Carbon\CarbonImmutable;
use Modules\Credits\Actions\ConsumeCredits;
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

    public function release(string $sessionId, ReleaseMode $mode, string $idempotencyKey, ?string $actorId = null, ?string $reason = null): void
    {
        $this->release->handle($sessionId, $mode, $idempotencyKey, $actorId, $reason);
    }

    public function isReserved(string $sessionId): bool
    {
        return CreditReservation::query()->where('session_id', $sessionId)->where('status', 'held')->exists();
    }

    public function balance(string $accountId): CreditBalance
    {
        $account = CreditAccount::query()->findOrFail($accountId);
        $next = CreditLot::query()->where('account_id', $accountId)->where('remaining_min', '>', 0)->whereNotNull('expires_at')
            ->orderBy('expires_at')->first(['expires_at', 'remaining_min']);

        return new CreditBalance(
            accountId: $account->id,
            availableMin: $account->available_min,
            reservedMin: $account->reserved_min,
            consumedMin: $account->consumed_min,
            nextExpiry: $next?->expires_at,
            expiringMin: $next?->remaining_min ?? 0,
        );
    }
}
