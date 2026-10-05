<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Contracts\Events\SessionFlaggedForReview;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;

/**
 * Sends a session to "needs review": a technical problem, nobody showed, or
 * too little was taught. Credit stays reserved until someone decides.
 */
final class FlagForReview
{
    public const REASONS = ['technical', 'nobody_joined', 'short_delivery', 'dispute', 'other'];

    public function handle(Session $session, string $reason, ?string $note, ?string $actorId): Session
    {
        if (! in_array($reason, self::REASONS, true) || ! $session->status->canTransitionTo(SessionStatus::NeedsReview)) {
            throw ValidationException::withMessages(['session' => __('scheduling::board.errors.cannot_flag')]);
        }

        DB::transaction(function () use ($session, $reason, $note, $actorId) {
            $from = $session->status;
            $session->transitionTo(SessionStatus::NeedsReview, $reason);
            $session->save();

            SessionEvent::record($session, SessionEventKind::StatusChanged, array_filter([
                'from' => $from->value, 'to' => SessionStatus::NeedsReview->value, 'reason' => $reason, 'note' => $note,
            ]), $actorId);

            DB::afterCommit(fn () => event(new SessionFlaggedForReview($session->academy_id, $session->id, $reason)));
        });

        return $session;
    }
}
