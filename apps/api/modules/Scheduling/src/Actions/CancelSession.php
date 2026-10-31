<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Actions\Policy\PolicyDecider;
use Modules\Scheduling\Actions\Policy\RecordPolicyDecision;
use Modules\Scheduling\Contracts\Events\SessionCancelled;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;
use Modules\Scheduling\Domain\Policy\Decision;
use Modules\Scheduling\Domain\Policy\PolicyEvent;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Cancels a session under the policy (docs/architecture/02, section 4):
 * who asked and how long before the start decide what happens to the
 * student's credit, the teacher's pay and the performance record. A manager
 * may waive a forfeit (the credit comes back); the waiver is recorded.
 */
final class CancelSession
{
    /** Who asked => the reasons they can give. */
    public const REASONS = [
        'student' => ['illness', 'travel', 'school', 'family', 'technical', 'other'],
        'teacher' => ['illness', 'emergency', 'technical', 'other'],
        'academy' => ['closure', 'emergency', 'unpaid', 'teacher_leave', 'other'],
        'family' => ['substitute_refused'],             // turned down the substitute for their teacher on leave
        'system' => ['unpaid', 'holiday', 'teacher_leave', 'enrollment_paused', 'enrollment_ended', 'group_ended'],
    ];

    /** Lessons a paused or ended course (or an ended group) does not have. */
    public const COURSE_STOPPED = ['enrollment_paused', 'enrollment_ended', 'group_ended'];

    public function __construct(
        private readonly PolicyDecider $decider,
        private readonly RecordPolicyDecision $record,
        private readonly SettleSessionCredits $settle,
    ) {}

    /** What would happen, without doing it (for the cancel dialog). */
    public function preview(Session $session, string $askedBy, string $reason, ?CarbonImmutable $at = null): Decision
    {
        return $this->decider->decide($session, self::event($askedBy, $reason), $at)[0];
    }

    public function handle(Session $session, string $askedBy, string $reason, ?string $note, ?string $actorId, bool $waive = false, ?CarbonImmutable $at = null): Session
    {
        if (! in_array($reason, self::REASONS[$askedBy] ?? [], true)) {
            throw ValidationException::withMessages(['reason' => __('scheduling::board.errors.bad_reason')]);
        }
        if (! $session->status->canTransitionTo(SessionStatus::Cancelled)) {
            throw ValidationException::withMessages(['session' => __('scheduling::board.errors.cannot_cancel')]);
        }

        [$decision, $input, $version] = $this->decider->decide($session, self::event($askedBy, $reason), $at);
        if ($waive) {
            $decision = $decision->waived();
        }

        DB::transaction(function () use ($session, $askedBy, $reason, $note, $actorId, $waive, $decision, $input, $version) {
            $from = $session->status;
            $session->transitionTo(SessionStatus::Cancelled);
            $this->record->handle($session, $decision, $input, $version, ['by' => $askedBy, 'reason' => $reason, 'note' => $note, 'waived' => $waive], $actorId);
            $session->save();

            $session->participants()->update(['is_active' => false]);

            SessionEvent::record($session, SessionEventKind::StatusChanged, [
                'from' => $from->value, 'to' => SessionStatus::Cancelled->value, 'by' => $askedBy, 'reason' => $reason, 'credit' => $decision->credit,
            ], $actorId);
            $this->settle->handle($session, $decision->credit, $actorId, $version->policy()->get('makeup_validity_days'));

            if ($waive) {
                app(AuditTrail::class)->record('session.cancel_waived', 'session', $session->id, ['asked_by' => $askedBy, 'reason' => $reason, 'note' => $note, 'rule' => $decision->rule]);
            }
            DB::afterCommit(fn () => event(new SessionCancelled($session->academy_id, $session->id, $reason, $decision->credit === 'makeup', $actorId)));
        });

        return $session;
    }

    public static function event(string $askedBy, string $reason): PolicyEvent
    {
        // An academy holiday is rule 15; a teacher on leave, whoever cancels, is the academy not giving the lesson (rule 21: makeup).
        if ($reason === 'holiday') {
            return PolicyEvent::Closure;
        }
        if (in_array($reason, ['teacher_leave', 'substitute_refused'], true)) {
            return PolicyEvent::AcademyCancel;
        }
        // The course paused or ended (rule 22): the minutes come back, as they were.
        if (in_array($reason, self::COURSE_STOPPED, true)) {
            return PolicyEvent::CourseStopped;
        }

        return match ($askedBy) {
            'student' => PolicyEvent::StudentCancel,
            'teacher' => PolicyEvent::TeacherCancel,
            'system' => PolicyEvent::Unpaid,
            default => match ($reason) {
                'closure' => PolicyEvent::Closure,
                'emergency' => PolicyEvent::Emergency,
                'unpaid' => PolicyEvent::Unpaid,
                default => PolicyEvent::AcademyCancel,
            },
        };
    }
}
