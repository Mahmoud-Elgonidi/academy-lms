<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Contracts\Events\SessionTeacherSubstituted;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;
use Modules\Scheduling\Queries\AvailabilityQuery;
use Modules\Scheduling\Support\MinorsGuard;

/**
 * Another teacher covers one lesson (docs/architecture/02, section 5:
 * "substitute"): same time, same credit, the lesson remembers its own
 * teacher (original_teacher_id), and giving it back to them clears that.
 * The substitute must teach the subject and be on shift and free for the
 * whole lesson; the participants' exclusion keeps anyone from being booked
 * twice. The family may turn the substitute down for a makeup (RefuseSubstitute).
 */
final class SubstituteTeacher
{
    public function __construct(
        private readonly AvailabilityQuery $availability,
        private readonly MinorsGuard $minors,
    ) {}

    public function handle(Session $session, string $teacherId, ?string $actorId): Session
    {
        $l = 'scheduling::timeoff.errors.';
        if ($session->status !== SessionStatus::Scheduled || $session->starts_at->isPast()) {
            throw ValidationException::withMessages(['session' => __($l.'not_coverable')]);
        }
        if ($session->teacher_id === $teacherId) {
            throw ValidationException::withMessages(['teacher' => __('scheduling::policy.errors.same_teacher')]);
        }
        $teaches = DB::table('teacher_subjects')->where('teacher_id', $teacherId)->where('subject_id', $session->subject_id)->exists();
        if (! $teaches) {
            throw ValidationException::withMessages(['teacher' => __($l.'not_their_subject')]);
        }
        $this->minors->assert($teacherId, $session->participants()->where('role', ParticipantRole::Student->value)->pluck('user_id')->all());
        $free = $this->availability->forTeachers([$teacherId], $session->starts_at, $session->ends_at, $actorId)[$teacherId]->free ?? null;
        if ($free === null || ! $free->contains($session->starts_at->getTimestamp(), $session->ends_at->getTimestamp())) {
            throw ValidationException::withMessages(['teacher' => __('scheduling::policy.errors.teacher_busy')]);
        }

        $from = $session->teacher_id;
        $own = $session->original_teacher_id ?? $from;

        try {
            DB::transaction(function () use ($session, $teacherId, $from, $own, $actorId) {
                $session->teacher_id = $teacherId;
                $session->original_teacher_id = $teacherId === $own ? null : $own;
                $session->version++;
                $session->save();

                $session->participants()->where('role', ParticipantRole::Teacher->value)->update(['user_id' => $teacherId]);
                SessionEvent::record($session, SessionEventKind::TeacherChanged, ['from' => $from, 'to' => $teacherId, 'substitute' => $session->original_teacher_id !== null], $actorId);

                DB::afterCommit(fn () => event(new SessionTeacherSubstituted($session->academy_id, $session->id, $from, $teacherId, $actorId)));
            });
        } catch (QueryException $e) {
            $session->refresh();    // rolled back: the lesson as it stands, so another substitute can be tried on it
            if (str_contains($e->getMessage(), 'session_participants_no_double_booking')) {
                throw ValidationException::withMessages(['teacher' => __('scheduling::policy.errors.someone_busy')]);
            }

            throw $e;
        }

        return $session;
    }
}
