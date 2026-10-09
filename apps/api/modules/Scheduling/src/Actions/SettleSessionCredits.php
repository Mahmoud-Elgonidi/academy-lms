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
 * A session already settled and decided again moves its credit between used
 * and makeup through CreditsApi::correct.
 */
final class SettleSessionCredits
{
    public const EFFECTS = ['consumed', 'forfeit', 'refund', 'makeup', 'keep', 'none'];

    public function __construct(
        private readonly CreditsApi $credits,
        private readonly FundSessions $fund,
    ) {}

    /** @return int minutes a correction could not take back (makeup credit the family already used) */
    public function handle(Session $session, string $effect, ?string $actorId = null, ?int $makeupDays = null): int
    {
        $reserved = $this->credits->isReserved($session->id);

        // Decided before and decided again (a review, a dispute): the credit moves the other way, with new entries.
        if (! $reserved && $this->credits->settledAs($session->id) !== null) {
            return in_array($effect, ['consumed', 'forfeit', 'makeup'], true)
                ? $this->credits->correct($session->id, $effect, $actorId, $session->decision['review_reason'] ?? null, $makeupDays)
                : 0;
        }

        match ($effect) {
            'consumed' => $this->credits->consume($session->id, "session:{$session->id}:consume", 'session_'.$session->status->value),
            'forfeit' => $this->credits->consume($session->id, "session:{$session->id}:consume", 'forfeit'),
            'refund' => $this->credits->release($session->id, ReleaseMode::Refund, "session:{$session->id}:release", $actorId, $session->decision['reason'] ?? null),
            'makeup' => $this->credits->release($session->id, ReleaseMode::Makeup, "session:{$session->id}:release", $actorId, $session->decision['reason'] ?? $session->status->value, $makeupDays),
            default => null,
        };

        if ($effect === 'refund' && $reserved && $session->enrollment_id !== null) {
            $this->fund->handle($session->enrollment_id);
        }

        return 0;
    }
}
