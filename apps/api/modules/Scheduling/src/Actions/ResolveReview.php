<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Actions\Groups\GroupOutcomes;
use Modules\Scheduling\Contracts\Events\LessonDisputeDecided;
use Modules\Scheduling\Contracts\Events\SessionCancelled;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionDispute;
use Modules\Scheduling\Domain\Models\SessionEvent;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * A manager decides a session that needs review; the decision and its reason
 * are recorded. A lesson decided before (a dispute, a review opened on the
 * board) has its credit corrected between used and makeup; makeup minutes
 * the family already booked stay theirs (decision.credit_short). An open
 * dispute closes with the decision: upheld when the outcome changed,
 * rejected when it stayed, and the note is the answer its author reads.
 *
 * A group lesson is decided seat by seat ($studentId): that student's
 * outcome and credit (completed: attended; student absent: absent; the
 * teacher absent or a makeup: a makeup for them), and their own dispute.
 * The lesson leaves review once every seat is decided and no other seat's
 * dispute waits. Without a student it decides every seat alike, and the
 * teacher's absence or a makeup is the whole lesson's.
 */
final class ResolveReview
{
    public function __construct(
        private readonly SettleSessionCredits $settle,
        private readonly GroupOutcomes $groups,
    ) {}

    public const OUTCOMES = ['completed', 'teacher_absent', 'student_absent', 'cancelled_makeup'];

    public function handle(Session $session, string $outcome, ?string $note, string $actorId, ?string $studentId = null): Session
    {
        if ($session->status !== SessionStatus::NeedsReview || ! in_array($outcome, self::OUTCOMES, true)) {
            throw ValidationException::withMessages(['outcome' => __('scheduling::board.errors.cannot_resolve')]);
        }
        if ($session->isGroup()) {
            return $this->group($session, $outcome, $note, $actorId, $studentId);
        }

        // The teacher's share of the pay, as the policy has it (Payroll reads it from the decision).
        [$to, $decision] = match ($outcome) {
            'completed' => [SessionStatus::Completed, ['credit' => 'consumed', 'teacher_pay_pct' => 100]],
            'teacher_absent' => [SessionStatus::TeacherAbsent, ['credit' => 'makeup', 'teacher_pay_pct' => 0]],
            'student_absent' => [SessionStatus::StudentAbsent, ['credit' => 'consumed', 'teacher_pay_pct' => PolicyVersion::settingsInForce()->get('student_absent_teacher_pay_pct')]],
            'cancelled_makeup' => [SessionStatus::Cancelled, ['credit' => 'makeup', 'teacher_pay_pct' => 0]],
        };

        DB::transaction(function () use ($session, $to, $decision, $outcome, $note, $actorId) {
            $dispute = SessionDispute::query()->where('session_id', $session->id)->where('status', 'open')->lockForUpdate()->first();
            if ($dispute !== null && trim((string) $note) === '') {
                throw ValidationException::withMessages(['note' => __('scheduling::disputes.errors.answer')]);
            }

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
            $short = $this->settle->handle($session, $decision['credit'], $actorId);
            if ($short > 0) {
                $session->decision = $session->decision + ['credit_short' => $short];
                $session->save();
            }

            if ($dispute !== null) {
                $dispute->fill([
                    'status' => $to->value === $dispute->outcome_before ? 'rejected' : 'upheld',
                    'outcome_after' => $to->value, 'decided_by' => $actorId, 'decided_at' => CarbonImmutable::now(), 'decision_note' => trim((string) $note),
                ])->save();
                DB::afterCommit(fn () => event(new LessonDisputeDecided($session->academy_id, $dispute->id, $session->id, $dispute->status, $dispute->outcome_before, $to->value)));
            }

            app(AuditTrail::class)->record('session.review_resolved', 'session', $session->id, array_filter([
                'review_reason' => $reason, 'outcome' => $outcome, 'note' => $note, 'dispute' => $dispute?->status, 'credit_short' => $short ?: null,
            ], fn ($v) => $v !== null));

            DB::afterCommit(fn () => $to === SessionStatus::Cancelled
                ? event(new SessionCancelled($session->academy_id, $session->id, 'review', true, $actorId))
                : event(new SessionFinalized($session->academy_id, $session->id, $to->value, $session->decision)));
        });

        return $session;
    }

    private function group(Session $session, string $outcome, ?string $note, string $actorId, ?string $studentId): Session
    {
        $seatOutcome = match ($outcome) {
            'completed' => GroupOutcomes::ATTENDED,
            'student_absent' => GroupOutcomes::ABSENT,
            default => GroupOutcomes::MAKEUP,
        };

        DB::transaction(function () use ($session, $outcome, $seatOutcome, $note, $actorId, $studentId) {
            $open = SessionDispute::query()->where('session_id', $session->id)->where('status', 'open')->lockForUpdate()->get();
            $answering = $studentId === null ? $open : $open->where('student_id', $studentId);
            if ($answering->isNotEmpty() && trim((string) $note) === '') {
                throw ValidationException::withMessages(['note' => __('scheduling::disputes.errors.answer')]);
            }

            $reason = $session->review_reason;
            $seats = $this->groups->seats($session)->filter(fn ($p) => $p->outcome !== GroupOutcomes::EXCUSED);
            $outcomes = $studentId !== null
                ? [$studentId => $seatOutcome]
                : $seats->mapWithKeys(fn ($p) => [$p->user_id => $seatOutcome])->all();
            $whole = $studentId === null ? match ($outcome) {
                'teacher_absent' => SessionStatus::TeacherAbsent,
                'cancelled_makeup' => SessionStatus::Cancelled,
                default => null,
            } : null;

            $done = $this->groups->apply($session, $outcomes, $actorId, 'admin', null,
                ['review_reason' => $reason, 'note' => $note, 'resolution' => $outcome], $whole, keepOpen: $open->count() > $answering->count());

            $seatNow = $this->groups->seats($session)->keyBy('user_id');
            foreach ($answering as $dispute) {
                $after = GroupOutcomes::AS_STATUS[$seatNow[$dispute->student_id]->outcome ?? ''] ?? $session->status->value;
                $dispute->fill([
                    'status' => $after === $dispute->outcome_before ? 'rejected' : 'upheld',
                    'outcome_after' => $after, 'decided_by' => $actorId, 'decided_at' => CarbonImmutable::now(), 'decision_note' => trim((string) $note),
                ])->save();
                DB::afterCommit(fn () => event(new LessonDisputeDecided($session->academy_id, $dispute->id, $session->id, $dispute->status, $dispute->outcome_before, $after)));
            }

            app(AuditTrail::class)->record('session.review_resolved', 'session', $session->id, array_filter([
                'review_reason' => $reason, 'outcome' => $outcome, 'note' => $note, 'seat' => $studentId !== null ?: null,
                'dispute' => $answering->first()?->status, 'credit_short' => $done['short'] ?: null,
            ], fn ($v) => $v !== null));
        });

        return $session;
    }
}
