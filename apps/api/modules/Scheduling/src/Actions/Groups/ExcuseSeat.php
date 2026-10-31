<?php

namespace Modules\Scheduling\Actions\Groups;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\Policy\PolicyDecider;
use Modules\Scheduling\Actions\SettleSessionCredits;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;
use Modules\Scheduling\Domain\Models\SessionParticipant;
use Modules\Scheduling\Domain\Policy\Decision;
use Modules\Scheduling\Domain\Policy\PolicyEvent;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * "I won't attend" for a group lesson (docs/architecture/02, "الحصص
 * الجماعية"): the family never cancels a lesson others are in; the
 * student's own seat is excused instead, and the policy's notice rule says
 * what it does to their credit, on their own course (in time: back to the
 * balance and their quota counted; late: used). The lesson goes on for
 * everyone else, and the student is out of its room.
 */
final class ExcuseSeat
{
    public function __construct(
        private readonly PolicyDecider $decider,
        private readonly SettleSessionCredits $settle,
        private readonly GroupSeats $seats,
        private readonly AuditTrail $audit,
    ) {}

    /** What excusing would do, without doing it (for the "I won't attend" dialog). */
    public function preview(Session $session, string $studentId, ?CarbonImmutable $at = null): Decision
    {
        return $this->decider->decide($session, PolicyEvent::StudentCancel, $at, $this->seat($session, $studentId)->enrollment_id)[0];
    }

    public function handle(Session $session, string $studentId, string $reason, ?string $note, ?string $actorId, bool $waive = false, ?CarbonImmutable $at = null): SessionParticipant
    {
        if (! in_array($reason, CancelSession::REASONS['student'], true)) {
            throw ValidationException::withMessages(['reason' => __('scheduling::board.errors.bad_reason')]);
        }
        if ($session->status !== SessionStatus::Scheduled) {
            throw ValidationException::withMessages(['session' => __('scheduling::board.errors.cannot_cancel')]);
        }
        $seat = $this->seat($session, $studentId);
        [$decision, $input, $version] = $this->decider->decide($session, PolicyEvent::StudentCancel, $at, $seat->enrollment_id);
        if ($waive) {
            $decision = $decision->waived();
        }
        if (! $decision->allowed) {
            throw ValidationException::withMessages(['session' => __((string) $decision->refusal)]);
        }

        return DB::transaction(function () use ($session, $seat, $reason, $note, $actorId, $waive, $decision, $input, $version, $at) {
            $session->version++;
            $session->save();

            $seat->outcome = GroupOutcomes::EXCUSED;
            $seat->is_active = false;
            $seat->save();

            SessionEvent::record($session, SessionEventKind::PolicyApplied, array_filter([
                'input' => $input->toArray(), 'decision' => $decision->toArray(), 'policy_version' => $version->version,
                'by' => 'student', 'reason' => $reason, 'note' => $note, 'waived' => $waive ?: null,
                'student_id' => $seat->user_id, 'enrollment_id' => $seat->enrollment_id,
            ], fn ($v) => $v !== null), $actorId, $at);
            SessionEvent::record($session, SessionEventKind::DecisionLogged, ['decision' => 'student_excused', 'user_id' => $seat->user_id, 'reason' => $reason, 'credit' => $decision->credit], $actorId, $at);

            $this->settle->settleSeat($session, (string) $seat->enrollment_id, $decision->credit, $actorId, PolicyVersion::settingsInForce()->get('makeup_validity_days'));
            $this->seats->syncFunding($session);

            if ($waive) {
                $this->audit->record('session.cancel_waived', 'session', $session->id, ['asked_by' => 'student', 'reason' => $reason, 'note' => $note, 'rule' => $decision->rule, 'seat' => true]);
            }

            return $seat;
        });
    }

    private function seat(Session $session, string $studentId): SessionParticipant
    {
        if (! $session->isGroup()) {
            throw ValidationException::withMessages(['session' => __('scheduling::groups.errors.not_group_lesson')]);
        }
        $seat = SessionParticipant::query()->where('session_id', $session->id)->where('user_id', $studentId)
            ->where('role', ParticipantRole::Student->value)->whereNotNull('enrollment_id')->first();
        if ($seat === null || ! $seat->is_active || $seat->outcome !== null) {
            throw ValidationException::withMessages(['student' => __('scheduling::group_outcomes.errors.no_seat')]);
        }

        return $seat;
    }
}
