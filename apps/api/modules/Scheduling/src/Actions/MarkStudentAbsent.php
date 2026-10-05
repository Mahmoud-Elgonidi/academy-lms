<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;

/**
 * Closes a session as "student absent" (default rule 5): the teacher is in,
 * the student never came. Done by the sweeper once the wait is over, or by a
 * coordinator earlier when the guardian says the student is not coming.
 */
final class MarkStudentAbsent
{
    public function handle(Session $session, ?string $actorId, string $by = 'admin', ?CarbonImmutable $at = null): Session
    {
        $teacher = $session->participant(ParticipantRole::Teacher);
        $student = $session->participant(ParticipantRole::Student);

        if ($teacher?->first_join_at === null || $student?->first_join_at !== null) {
            throw ValidationException::withMessages(['session' => __('scheduling::board.errors.not_student_absent')]);
        }

        DB::transaction(function () use ($session, $actorId, $by, $at) {
            $from = $session->status;
            $session->transitionTo(SessionStatus::StudentAbsent);
            $session->decision = ['by' => $by, 'rule' => 5, 'credit' => 'consumed', 'teacher_pay' => 'full'];
            $session->save();

            SessionEvent::record($session, SessionEventKind::StatusChanged, [
                'from' => $from->value, 'to' => SessionStatus::StudentAbsent->value, 'by' => $by,
            ], $actorId, $at);

            DB::afterCommit(fn () => event(new SessionFinalized($session->academy_id, $session->id, SessionStatus::StudentAbsent->value, $session->decision)));
        });

        return $session;
    }
}
