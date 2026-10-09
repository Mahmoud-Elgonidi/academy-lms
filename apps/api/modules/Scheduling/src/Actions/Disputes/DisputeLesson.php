<?php

namespace Modules\Scheduling\Actions\Disputes;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\Scheduling\Actions\FlagForReview;
use Modules\Scheduling\Contracts\Events\LessonDisputed;
use Modules\Scheduling\Domain\Disputes\DisputeRules;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
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
    public function handle(Session $session, string $side, string $raisedBy, string $reason, string $note): SessionDispute
    {
        $session->loadMissing('participants');
        $this->ensureMayDispute($session, $side, $raisedBy);

        $note = trim($note);
        $e = 'scheduling::disputes.errors.';
        if (! DisputeRules::open($session->status->value, $session->ends_at, CarbonImmutable::now())) {
            throw ValidationException::withMessages(['session' => __($e.'closed', ['hours' => DisputeRules::HOURS])]);
        }
        if (! DisputeRules::reasonFits($side, $reason, $session->status->value)) {
            throw ValidationException::withMessages(['reason' => __($e.'reason')]);
        }
        if ($note === '' || mb_strlen($note) > self::NOTE_MAX) {
            throw ValidationException::withMessages(['note' => __($e.'note', ['max' => self::NOTE_MAX])]);
        }

        try {
            return DB::transaction(function () use ($session, $side, $raisedBy, $reason, $note) {
                $dispute = SessionDispute::query()->create([
                    'session_id' => $session->id, 'raised_by' => $raisedBy, 'side' => $side, 'reason' => $reason, 'note' => $note,
                    'outcome_before' => $session->status->value,
                ]);
                $this->flag->handle($session, 'dispute', mb_substr($note, 0, 500), $raisedBy);
                $this->audit->record('session.disputed', 'session', $session->id, ['side' => $side, 'reason' => $reason, 'outcome_before' => $dispute->outcome_before]);

                DB::afterCommit(fn () => event(new LessonDisputed($session->academy_id, $dispute->id, $session->id, $side, $reason)));

                return $dispute;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['session' => __($e.'already')]);
        }
    }

    /** The teacher of the lesson for "teacher"; for "family" the student or someone who follows them, out of the kids' look. */
    private function ensureMayDispute(Session $session, string $side, string $raisedBy): void
    {
        $student = $session->participants->firstWhere('role', ParticipantRole::Student)?->user_id;
        $allowed = match ($side) {
            'teacher' => $session->teacher_id === $raisedBy,
            'family' => $student !== null && $this->family->follows($raisedBy, $student) && $this->family->stage($raisedBy) !== LearnerStage::Kids,
            default => false,
        };
        if (! $allowed) {
            throw new AccessDeniedHttpException(__('scheduling::disputes.errors.not_yours'));
        }
    }
}
