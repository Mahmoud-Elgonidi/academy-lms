<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;
use Modules\Scheduling\Domain\Models\SessionParticipant;

/** Adds minutes to a running session (lost time after a drop), if neither person is booked right after. */
final class ExtendSession
{
    public const ALLOWED = [5, 10, 15];

    public function handle(Session $session, int $minutes, string $actorId): Session
    {
        if (! in_array($minutes, self::ALLOWED, true) || ! $session->status->isOpen()) {
            throw ValidationException::withMessages(['minutes' => __('scheduling::board.errors.cannot_extend')]);
        }

        try {
            DB::transaction(function () use ($session, $minutes, $actorId) {
                $previousEnd = $session->ends_at;
                $session->ends_at = $session->ends_at->addMinutes($minutes);
                $session->version++;
                $session->save();

                $session->participants()->where('is_active', true)->update([
                    'block_period' => SessionParticipant::blockPeriod($session->starts_at, $session->ends_at),
                ]);

                SessionEvent::record($session, SessionEventKind::Extended, [
                    'minutes' => $minutes,
                    'from' => $previousEnd->toIso8601String(),
                    'to' => $session->ends_at->toIso8601String(),
                ], $actorId);
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'session_participants_no_double_booking')) {
                $session->refresh();
                throw ValidationException::withMessages(['minutes' => __('scheduling::board.errors.extend_overlap')]);
            }

            throw $e;
        }

        return $session;
    }
}
