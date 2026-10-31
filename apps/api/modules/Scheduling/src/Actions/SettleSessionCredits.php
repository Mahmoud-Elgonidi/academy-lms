<?php

namespace Modules\Scheduling\Actions;

use Modules\Credits\Contracts\CreditsApi;
use Modules\Credits\Contracts\ReleaseMode;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionParticipant;

/**
 * What a closed session does to the student's credit, by the decision's
 * "credit" (docs/architecture/02, section 5, "تأثير كل حالة على الرصيد"):
 * consumed or forfeit use the reserved minutes, refund gives them back (and
 * they fund the next waiting session), makeup turns them into a 30-day
 * makeup lot. Called inside the transaction that closes the session; a
 * session with no reservation (a free trial, an unpaid lesson) changes nothing.
 * A session already settled and decided again moves its credit between used
 * and makeup through CreditsApi::correct.
 *
 * A group lesson settles seat by seat: each student's own reservation, on
 * their own account, with the same effect (a decision for the whole lesson,
 * such as the group's lesson cancelled or its teacher absent), skipping a
 * seat already excused; one student's own outcome goes through settleSeat()
 * (Groups\GroupOutcomes).
 */
final class SettleSessionCredits
{
    public const EFFECTS = ['consumed', 'forfeit', 'refund', 'makeup', 'keep', 'none'];

    public function __construct(
        private readonly CreditsApi $credits,
        private readonly FundSessions $fund,
        private readonly EnrollmentApi $enrollments,
    ) {}

    /** @return int minutes a correction could not take back (makeup credit the family already used) */
    public function handle(Session $session, string $effect, ?string $actorId = null, ?int $makeupDays = null): int
    {
        if ($session->group_id !== null) {
            $short = 0;
            $seats = SessionParticipant::query()->where('session_id', $session->id)->where('role', ParticipantRole::Student->value)
                ->whereNotNull('enrollment_id')
                ->where(fn ($q) => $q->whereNull('outcome')->orWhere('outcome', '!=', 'excused'))     // an excused seat was settled when excused
                ->get(['user_id', 'enrollment_id']);
            foreach ($seats as $seat) {
                $short += $this->settleSeat($session, (string) $seat->enrollment_id, $effect, $actorId, $makeupDays);
            }

            return $short;
        }

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

    /** One student's seat in a group lesson: their own account's reservation, keyed by it. */
    public function settleSeat(Session $session, string $enrollmentId, string $effect, ?string $actorId = null, ?int $makeupDays = null): int
    {
        $accountId = $this->enrollments->find($enrollmentId)?->creditAccountId;
        if ($accountId === null) {
            return 0;
        }
        $reserved = $this->credits->isReservedFor($session->id, $accountId);
        if (! $reserved && $this->credits->settledAsFor($session->id, $accountId) !== null) {
            return in_array($effect, ['consumed', 'forfeit', 'makeup'], true)
                ? $this->credits->correctFor($session->id, $accountId, $effect, $actorId, $session->decision['review_reason'] ?? null, $makeupDays)
                : 0;
        }
        $key = "session:{$session->id}:account:{$accountId}";
        match ($effect) {
            'consumed' => $this->credits->consumeFor($session->id, $accountId, "{$key}:consume", 'session_'.$session->status->value),
            'forfeit' => $this->credits->consumeFor($session->id, $accountId, "{$key}:consume", 'forfeit'),
            'refund' => $this->credits->releaseFor($session->id, $accountId, ReleaseMode::Refund, "{$key}:release", $actorId, $session->decision['reason'] ?? null),
            'makeup' => $this->credits->releaseFor($session->id, $accountId, ReleaseMode::Makeup, "{$key}:release", $actorId, $session->decision['reason'] ?? $session->status->value, $makeupDays),
            default => null,
        };
        if ($effect === 'refund' && $reserved) {
            $this->fund->handle($enrollmentId);
        }

        return 0;
    }
}
