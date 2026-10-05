<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Contracts\Events\SessionCancelled;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;

/**
 * Cancels a session from the back office. Frees both people's time and
 * records why; with $makeup the student is owed a makeup session (Credits
 * grants it when it lands, listening to SessionCancelled).
 */
final class CancelSession
{
    public const REASONS = ['teacher_absent', 'student_request', 'unpaid', 'technical', 'emergency', 'other'];

    public function handle(Session $session, string $reason, bool $makeup, ?string $note, string $actorId): Session
    {
        if (! in_array($reason, self::REASONS, true)) {
            throw ValidationException::withMessages(['reason' => __('scheduling::board.errors.bad_reason')]);
        }
        if (! $session->status->canTransitionTo(SessionStatus::Cancelled)) {
            throw ValidationException::withMessages(['session' => __('scheduling::board.errors.cannot_cancel')]);
        }

        DB::transaction(function () use ($session, $reason, $makeup, $note, $actorId) {
            $from = $session->status;
            $session->transitionTo(SessionStatus::Cancelled);
            $session->decision = [
                'by' => 'admin',
                'reason' => $reason,
                'makeup' => $makeup,
                'note' => $note,
            ];
            $session->save();

            $session->participants()->update(['is_active' => false]);

            SessionEvent::record($session, SessionEventKind::StatusChanged, [
                'from' => $from->value, 'to' => SessionStatus::Cancelled->value, 'reason' => $reason, 'makeup' => $makeup,
            ], $actorId);

            DB::afterCommit(fn () => event(new SessionCancelled($session->academy_id, $session->id, $reason, $makeup, $actorId)));
        });

        return $session;
    }
}
