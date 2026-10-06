<?php

namespace Modules\Credits\Contracts;

use Carbon\CarbonImmutable;
use Modules\Credits\Contracts\DTOs\CreditBalance;

/**
 * The credit ledger, in minutes (docs/architecture/04, "Ledger"). Every write
 * takes an idempotency key: the same key twice does the work once.
 * Callers run these inside their own transaction when the change must land
 * together with theirs (a payment and its grant, a booking and its reservation).
 */
interface CreditsApi
{
    /** The account for this scope, opened if it does not exist yet. */
    public function openAccount(string $studentId, string $scopeType, ?string $scopeId, ?int $lowBalanceMin = null): string;

    public function setLowBalance(string $accountId, ?int $minutes): void;

    /** Adds a lot and returns its id. */
    public function grant(string $accountId, LotSource $source, ?string $sourceId, int $minutes, ?CarbonImmutable $expiresAt, string $idempotencyKey, ?string $actorId = null, ?string $reason = null): string;

    /**
     * Sets minutes aside for a session from lots still valid at $usableAt;
     * false when there are not enough. Makeup lots pay for makeup sessions, and makeup sessions for nothing else.
     */
    public function reserve(string $sessionId, string $accountId, int $minutes, CarbonImmutable $usableAt, string $idempotencyKey, bool $forMakeup = false): bool;

    /** The session happened (or was forfeited): its reserved minutes are used. No-op without a reservation. */
    public function consume(string $sessionId, string $idempotencyKey, ?string $reason = null): void;

    /**
     * The session did not happen: its minutes come back, as they were or as a
     * makeup lot valid $makeupDays (30 by default). No-op without a reservation.
     */
    public function release(string $sessionId, ReleaseMode $mode, string $idempotencyKey, ?string $actorId = null, ?string $reason = null, ?int $makeupDays = null): void;

    /** Lots not yet lapsed expire $days later (a holiday gives its days back); returns how many moved. */
    public function extend(string $accountId, int $days, string $idempotencyKey, ?string $actorId = null, ?string $reason = null): int;

    public function isReserved(string $sessionId): bool;

    public function balance(string $accountId): CreditBalance;
}
