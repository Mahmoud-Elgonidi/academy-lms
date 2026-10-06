<?php

namespace Modules\Scheduling\Actions;

use Modules\Credits\Contracts\CreditsApi;
use Modules\Credits\Contracts\ReleaseMode;
use Modules\Scheduling\Domain\Models\Session;

/**
 * What a closed session does to the student's credit, by the decision's
 * "credit" (docs/architecture/02, section 5, "تأثير كل حالة على الرصيد"):
 * consumed or forfeit use the reserved minutes, refund gives them back (and
 * they fund the next waiting session), makeup turns them into a 30-day
 * makeup lot. Called inside the transaction that closes the session; a
 * session with no reservation (a free trial, an unpaid lesson) changes nothing.
 */
final class SettleSessionCredits
{
    public const EFFECTS = ['consumed', 'forfeit', 'refund', 'makeup', 'none'];

    public function __construct(
        private readonly CreditsApi $credits,
        private readonly FundSessions $fund,
    ) {}

    public function handle(Session $session, string $effect, ?string $actorId = null): void
    {
        $reserved = $this->credits->isReserved($session->id);

        match ($effect) {
            'consumed' => $this->credits->consume($session->id, "session:{$session->id}:consume", 'session_'.$session->status->value),
            'forfeit' => $this->credits->consume($session->id, "session:{$session->id}:consume", 'forfeit'),
            'refund' => $this->credits->release($session->id, ReleaseMode::Refund, "session:{$session->id}:release", $actorId, $session->decision['reason'] ?? null),
            'makeup' => $this->credits->release($session->id, ReleaseMode::Makeup, "session:{$session->id}:release", $actorId, $session->decision['reason'] ?? $session->status->value),
            default => null,
        };

        if ($effect === 'refund' && $reserved && $session->enrollment_id !== null) {
            $this->fund->handle($session->enrollment_id);
        }
    }
}
