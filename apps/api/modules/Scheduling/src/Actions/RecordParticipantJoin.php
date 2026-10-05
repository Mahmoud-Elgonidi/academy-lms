<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;

/**
 * Someone entered the classroom (from the video provider's webhook once
 * Classroom lands). The teacher joining starts the session.
 */
final class RecordParticipantJoin
{
    public function handle(Session $session, string $userId, ?CarbonImmutable $at = null): void
    {
        $at ??= CarbonImmutable::now();

        DB::transaction(function () use ($session, $userId, $at) {
            $participant = $session->participants()->where('user_id', $userId)->firstOrFail();
            if ($participant->first_join_at === null) {
                $participant->first_join_at = $at;
                $participant->save();
            }

            SessionEvent::record($session, SessionEventKind::Joined, ['user_id' => $userId, 'role' => $participant->role->value], $userId, $at);

            if ($participant->role === ParticipantRole::Teacher && $session->status === SessionStatus::Scheduled) {
                $session->transitionTo(SessionStatus::InProgress);
                $session->save();
            }
        });
    }
}
