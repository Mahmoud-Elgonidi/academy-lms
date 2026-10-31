<?php

namespace Modules\Credits\Contracts;

use Carbon\CarbonImmutable;
use Modules\Credits\Contracts\DTOs\CreditBalance;

/**
 * The credit ledger, in minutes (docs/architecture/04, "Ledger"). Every write
 * takes an idempotency key: the same key twice does the work once.
 * Callers run these inside their own transaction when the change must land
 * together with theirs (a payment and its grant, a booking and its reservation).
 *
 * A one-to-one lesson holds one reservation, so the calls keyed by the session
 * alone act on it. A group lesson holds one for each student, each from their
 * own account: the *For calls name it (idempotency keys
 * "session:{id}:account:{account}:…"), and the session-only calls refuse it.
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

    /** How a session's credit was settled (consumed, makeup, refund); null while held, or with no reservation. */
    public function settledAs(string $sessionId): ?string;

    /**
     * A session decided again (a review, a dispute): its settled credit moves to $effect (consumed or makeup) with
     * new entries. Returns the minutes that could not be taken back (makeup credit already used). No-op without a
     * reservation or when nothing changes.
     */
    public function correct(string $sessionId, string $effect, ?string $actorId = null, ?string $reason = null, ?int $makeupDays = null): int;

    /**
     * A makeup lot outside any lesson (a late join's missed lessons), valid
     * $days, kept for private makeup (with the course's teacher), group
     * makeup (a free seat in another group's lesson), or either (null).
     */
    public function grantMakeup(string $accountId, int $minutes, int $days, ?string $sourceId, string $idempotencyKey, ?string $actorId = null, ?string $reason = null, ?string $useFor = null): string;

    /** reserve() for one student's seat in a lesson; $makeupFor (private or group) picks the makeup lots that may pay. */
    public function reserveFor(string $sessionId, string $accountId, int $minutes, CarbonImmutable $usableAt, string $idempotencyKey, bool $forMakeup = false, string $makeupFor = 'private'): bool;

    public function consumeFor(string $sessionId, string $accountId, string $idempotencyKey, ?string $reason = null): void;

    public function releaseFor(string $sessionId, string $accountId, ReleaseMode $mode, string $idempotencyKey, ?string $actorId = null, ?string $reason = null, ?int $makeupDays = null): void;

    public function correctFor(string $sessionId, string $accountId, string $effect, ?string $actorId = null, ?string $reason = null, ?int $makeupDays = null): int;

    public function isReservedFor(string $sessionId, string $accountId): bool;

    public function settledAsFor(string $sessionId, string $accountId): ?string;

    public function balance(string $accountId): CreditBalance;

    /**
     * Several accounts at once, for screens that look at many courses.
     *
     * @param  list<string>  $accountIds
     * @return array<string, CreditBalance> by account id; an unknown id is left out
     */
    public function balances(array $accountIds): array;
}
