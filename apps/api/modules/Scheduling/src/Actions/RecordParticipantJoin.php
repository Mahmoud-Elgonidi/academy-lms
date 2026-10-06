<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\AttendanceSegment;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;

/**
 * Someone entered the classroom (the video provider's webhook, its room API,
 * or a click on an outside link): a stretch opens unless one already is.
 * The teacher joining starts the session.
 */
final class RecordParticipantJoin
{
    public function handle(Session $session, string $userId, ?CarbonImmutable $at = null, string $source = 'provider'): void
    {
        $at ??= CarbonImmutable::now();

        DB::transaction(function () use ($session, $userId, $at, $source) {
            $participant = $session->participants()->where('user_id', $userId)->firstOrFail();
            if ($participant->first_join_at === null) {
                $participant->first_join_at = $at;
                $participant->save();
            }

            $open = AttendanceSegment::query()->where('session_id', $session->id)->where('user_id', $userId)->whereNull('left_at')->lockForUpdate()->exists();
            if ($open) {
                return;     // already in (a repeated webhook, or the room API catching up)
            }
            AttendanceSegment::query()->create(['session_id' => $session->id, 'user_id' => $userId, 'role' => $participant->role->value, 'joined_at' => $at, 'source' => $source]);

            SessionEvent::record($session, SessionEventKind::Joined, ['user_id' => $userId, 'role' => $participant->role->value, 'source' => $source], $userId, $at);

            if ($participant->role === ParticipantRole::Teacher && $session->status === SessionStatus::Scheduled) {
                $session->transitionTo(SessionStatus::InProgress);
                $session->save();
            }
        });
    }
}
