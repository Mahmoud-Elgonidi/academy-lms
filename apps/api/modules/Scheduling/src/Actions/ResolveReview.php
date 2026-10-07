<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Contracts\Events\SessionCancelled;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;
use Modules\SharedKernel\Audit\AuditTrail;

/** A manager decides a session that needs review; the decision and its reason are recorded. */
final class ResolveReview
{
    public function __construct(private readonly SettleSessionCredits $settle) {}

    public const OUTCOMES = ['completed', 'teacher_absent', 'student_absent', 'cancelled_makeup'];

    public function handle(Session $session, string $outcome, ?string $note, string $actorId): Session
    {
        if ($session->status !== SessionStatus::NeedsReview || ! in_array($outcome, self::OUTCOMES, true)) {
            throw ValidationException::withMessages(['outcome' => __('scheduling::board.errors.cannot_resolve')]);
        }

        // The teacher's share of the pay, as the policy has it (Payroll reads it from the decision).
        [$to, $decision] = match ($outcome) {
            'completed' => [SessionStatus::Completed, ['credit' => 'consumed', 'teacher_pay_pct' => 100]],
            'teacher_absent' => [SessionStatus::TeacherAbsent, ['credit' => 'makeup', 'teacher_pay_pct' => 0]],
            'student_absent' => [SessionStatus::StudentAbsent, ['credit' => 'consumed', 'teacher_pay_pct' => PolicyVersion::settingsInForce()->get('student_absent_teacher_pay_pct')]],
            'cancelled_makeup' => [SessionStatus::Cancelled, ['credit' => 'makeup', 'teacher_pay_pct' => 0]],
        };

        DB::transaction(function () use ($session, $to, $decision, $outcome, $note, $actorId) {
            $reason = $session->review_reason;
            $session->transitionTo($to);
            $session->decision = ['by' => 'admin', 'review_reason' => $reason, 'note' => $note] + $decision;
            $session->save();

            if ($to === SessionStatus::Cancelled) {
                $session->participants()->update(['is_active' => false]);
            }

            SessionEvent::record($session, SessionEventKind::StatusChanged, array_filter([
                'from' => SessionStatus::NeedsReview->value, 'to' => $to->value, 'resolution' => $outcome, 'note' => $note,
            ]), $actorId);
            $this->settle->handle($session, $decision['credit'], $actorId);

            app(AuditTrail::class)->record('session.review_resolved', 'session', $session->id, ['review_reason' => $reason, 'outcome' => $outcome, 'note' => $note]);

            DB::afterCommit(fn () => $to === SessionStatus::Cancelled
                ? event(new SessionCancelled($session->academy_id, $session->id, 'review', true, $actorId))
                : event(new SessionFinalized($session->academy_id, $session->id, $to->value, $session->decision)));
        });

        return $session;
    }
}
