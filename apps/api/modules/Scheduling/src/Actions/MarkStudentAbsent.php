<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Actions\Groups\GroupOutcomes;
use Modules\Scheduling\Actions\Policy\PolicyDecider;
use Modules\Scheduling\Actions\Policy\RecordPolicyDecision;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;
use Modules\Scheduling\Domain\Policy\PolicyEvent;

/**
 * Closes a session as "student absent" (default rule 5): the teacher is in,
 * the student never came. Done by the sweeper once the wait is over, or by a
 * coordinator earlier when the guardian says the student is not coming.
 * In a group lesson it is one student's seat ($studentId): theirs is
 * absent and settled on their own credit; the lesson goes on for the
 * others, and closes only once every seat is decided.
 */
final class MarkStudentAbsent
{
    public function __construct(
        private readonly SettleSessionCredits $settle,
        private readonly PolicyDecider $decider,
        private readonly RecordPolicyDecision $record,
        private readonly GroupOutcomes $groups,
    ) {}

    public function handle(Session $session, ?string $actorId, string $by = 'admin', ?CarbonImmutable $at = null, ?string $studentId = null): Session
    {
        if ($session->isGroup()) {
            return $this->seat($session, $studentId, $actorId, $by, $at);
        }

        $teacher = $session->participant(ParticipantRole::Teacher);
        $student = $session->participant(ParticipantRole::Student);

        if ($teacher?->first_join_at === null || $student?->first_join_at !== null) {
            throw ValidationException::withMessages(['session' => __('scheduling::board.errors.not_student_absent')]);
        }

        [$decision, $input, $version] = $this->decider->decide($session, PolicyEvent::StudentAbsent, $at);

        DB::transaction(function () use ($session, $actorId, $by, $at, $decision, $input, $version) {
            $from = $session->status;
            $session->transitionTo(SessionStatus::StudentAbsent);
            $this->record->handle($session, $decision, $input, $version, ['by' => $by], $actorId);
            $session->save();

            SessionEvent::record($session, SessionEventKind::StatusChanged, [
                'from' => $from->value, 'to' => SessionStatus::StudentAbsent->value, 'by' => $by,
            ], $actorId, $at);
            $this->settle->handle($session, $decision->credit, $actorId);

            DB::afterCommit(fn () => event(new SessionFinalized($session->academy_id, $session->id, SessionStatus::StudentAbsent->value, $session->decision)));
        });

        return $session;
    }

    private function seat(Session $session, ?string $studentId, ?string $actorId, string $by, ?CarbonImmutable $at): Session
    {
        $session->loadMissing('participants');
        $teacher = $session->participant(ParticipantRole::Teacher);
        $seat = $studentId === null ? null : $session->participants->first(fn ($p) => $p->role === ParticipantRole::Student && $p->user_id === $studentId && $p->is_active);
        if ($seat === null) {
            throw ValidationException::withMessages(['student' => __('scheduling::group_outcomes.errors.no_seat')]);
        }
        if (! $session->status->isOpen() || $teacher?->first_join_at === null || $seat->first_join_at !== null || $seat->outcome !== null) {
            throw ValidationException::withMessages(['session' => __('scheduling::board.errors.not_student_absent')]);
        }
        $this->groups->apply($session, [$studentId => GroupOutcomes::ABSENT], $actorId, $by, $at);

        return $session;
    }
}
