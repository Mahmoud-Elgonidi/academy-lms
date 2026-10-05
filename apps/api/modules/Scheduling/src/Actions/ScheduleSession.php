<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionType;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;
use Modules\Scheduling\Domain\Models\SessionParticipant;

/**
 * Books one session for a teacher and a student. The database refuses a
 * second booking that overlaps either of them (exclusion constraint), so two
 * coordinators cannot book the same hour at once.
 */
final class ScheduleSession
{
    public function handle(NewSession $new, ?string $actorId = null): Session
    {
        $endsAt = $new->startsAt->addMinutes($new->durationMin);

        try {
            return DB::transaction(function () use ($new, $endsAt, $actorId) {
                $session = Session::query()->create([
                    'type' => SessionType::from($new->type),
                    'teacher_id' => $new->teacherId,
                    'subject_id' => $new->subjectId,
                    'starts_at' => $new->startsAt->utc(),
                    'ends_at' => $endsAt->utc(),
                    'funding_status' => FundingStatus::from($new->funding),
                    'anchor_tz' => $new->anchorTimezone,
                ]);

                $period = SessionParticipant::blockPeriod($new->startsAt, $endsAt);
                foreach ([ParticipantRole::Teacher->value => $new->teacherId, ParticipantRole::Student->value => $new->studentId] as $role => $userId) {
                    SessionParticipant::query()->create([
                        'session_id' => $session->id,
                        'user_id' => $userId,
                        'role' => $role,
                        'block_period' => $period,
                    ]);
                }

                SessionEvent::record($session, SessionEventKind::Scheduled, [], $actorId);

                return $session;
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'session_participants_no_double_booking')) {
                throw ValidationException::withMessages(['starts_at' => __('scheduling::board.errors.double_booked')]);
            }

            throw $e;
        }
    }
}
