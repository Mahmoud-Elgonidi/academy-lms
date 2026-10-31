<?php

namespace Modules\Scheduling\Actions\Disputes;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\Scheduling\Actions\FlagForReview;
use Modules\Scheduling\Actions\Groups\GroupOutcomes;
use Modules\Scheduling\Contracts\Events\LessonDisputed;
use Modules\Scheduling\Domain\Disputes\DisputeRules;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionDispute;
use Modules\SharedKernel\Audit\AuditTrail;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The family (the student, or a guardian; never a child in the kids' look)
 * or the lesson's teacher says its outcome is wrong (docs/architecture/01,
 * section 8): within 48 hours of the end, once per lesson. The lesson goes
 * back to "needs review" (its pay line waits; its credit is corrected when
 * the academy decides), and the academy hears of it.
 *
 * A group lesson's dispute names a student's seat ($studentId; the family's
 * own learner when not given): it is about that seat's outcome, once per
 * student, and sends the lesson to review (its pay waits) while the other
 * seats keep theirs; another seat may still be disputed meanwhile.
 */
final class DisputeLesson
{
    public const NOTE_MAX = 1000;

    public function __construct(
        private readonly FlagForReview $flag,
        private readonly FamilyView $family,
        private readonly AuditTrail $audit,
    ) {}

    /** @param  'family'|'teacher'  $side */
    public function handle(Session $session, string $side, string $raisedBy, string $reason, string $note, ?string $studentId = null): SessionDispute
    {
        $session->loadMissing('participants');
        $seat = $this->ensureMayDispute($session, $side, $raisedBy, $studentId);
        $outcome = $this->outcomeOf($session, $seat);

        $note = trim($note);
        $e = 'scheduling::disputes.errors.';
        $reviewing = $session->isGroup() && $session->status === SessionStatus::NeedsReview && $session->review_reason === 'dispute';
        if ($outcome === null || ! DisputeRules::open($reviewing ? $outcome : $session->status->value, $session->ends_at, CarbonImmutable::now())) {
            throw ValidationException::withMessages(['session' => __($e.'closed', ['hours' => DisputeRules::HOURS])]);
        }
        if (! DisputeRules::reasonFits($side, $reason, $outcome)) {
            throw ValidationException::withMessages(['reason' => __($e.'reason')]);
        }
        if ($note === '' || mb_strlen($note) > self::NOTE_MAX) {
            throw ValidationException::withMessages(['note' => __($e.'note', ['max' => self::NOTE_MAX])]);
        }

        try {
            return DB::transaction(function () use ($session, $side, $raisedBy, $reason, $note, $seat, $outcome, $reviewing) {
                $dispute = SessionDispute::query()->create([
                    'session_id' => $session->id, 'student_id' => $seat, 'raised_by' => $raisedBy, 'side' => $side, 'reason' => $reason, 'note' => $note,
                    'outcome_before' => $outcome,
                ]);
                if (! $reviewing) {
                    $this->flag->handle($session, 'dispute', mb_substr($note, 0, 500), $raisedBy);
                }
                $this->audit->record('session.disputed', 'session', $session->id, array_filter(['side' => $side, 'reason' => $reason, 'outcome_before' => $dispute->outcome_before, 'seat' => $session->isGroup() ?: null]));

                DB::afterCommit(fn () => event(new LessonDisputed($session->academy_id, $dispute->id, $session->id, $side, $reason)));

                return $dispute;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['session' => __($e.'already')]);
        }
    }

    /**
     * The teacher of the lesson for "teacher"; for "family" the student or someone who follows them, out of the kids' look.
     *
     * @return string the student whose seat the dispute is about
     */
    private function ensureMayDispute(Session $session, string $side, string $raisedBy, ?string $studentId): string
    {
        $students = $session->participants->filter(fn ($p) => $p->role === ParticipantRole::Student)->sortBy('user_id')->pluck('user_id');
        $student = match (true) {
            $studentId !== null => $students->contains($studentId) ? $studentId : null,
            $side === 'family' => $students->first(fn (string $id) => $this->family->follows($raisedBy, $id)),
            ! $session->isGroup() => $students->first(),
            default => null,     // a teacher names the seat of a group lesson
        };
        $allowed = match ($side) {
            'teacher' => $session->teacher_id === $raisedBy,
            'family' => $student !== null && $this->family->follows($raisedBy, $student) && $this->family->stage($raisedBy) !== LearnerStage::Kids,
            default => false,
        };
        if (! $allowed) {
            throw new AccessDeniedHttpException(__('scheduling::disputes.errors.not_yours'));
        }
        if ($student === null) {
            throw ValidationException::withMessages(['student' => __('scheduling::group_outcomes.errors.no_seat')]);
        }

        return $student;
    }

    /** What is disputed: the lesson's outcome, or in a group the seat's (as the status it stands for); null when it has none to dispute. */
    private function outcomeOf(Session $session, string $studentId): ?string
    {
        if (! $session->isGroup()) {
            return $session->status->value;
        }
        $seat = $session->participants->first(fn ($p) => $p->role === ParticipantRole::Student && $p->user_id === $studentId);

        return $seat?->outcome === null ? null : (GroupOutcomes::AS_STATUS[$seat->outcome] ?? null);
    }
}
