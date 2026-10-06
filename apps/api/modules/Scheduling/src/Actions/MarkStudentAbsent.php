<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
 */
final class MarkStudentAbsent
{
    public function __construct(
        private readonly SettleSessionCredits $settle,
        private readonly PolicyDecider $decider,
        private readonly RecordPolicyDecision $record,
    ) {}

    public function handle(Session $session, ?string $actorId, string $by = 'admin', ?CarbonImmutable $at = null): Session
    {
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
}
