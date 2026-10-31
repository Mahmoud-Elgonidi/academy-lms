<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Scheduling\Actions\Policy\PolicyDecider;
use Modules\Scheduling\Actions\Policy\RecordPolicyDecision;
use Modules\Scheduling\Contracts\Events\SessionRescheduled;
use Modules\Scheduling\Domain\Availability\IntervalSet;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;
use Modules\Scheduling\Domain\Models\SessionParticipant;
use Modules\Scheduling\Domain\Policy\PolicyEvent;
use Modules\Scheduling\Queries\AvailabilityQuery;
use Modules\Scheduling\Support\CourseDays;

/**
 * Moves one session to another time with the same teacher (rule 1): the
 * student may, with notice and within the block's quota; the academy may
 * always. The teacher must be on shift and free; the database refuses a
 * time either person is already booked. The credit stays reserved, and the
 * session no longer follows its weekly time. A course's lesson does not move
 * onto one of its paused days, nor past its end (CourseDays).
 *
 * A group lesson moves only for the whole group, and only by the academy
 * (a student's own change is a makeup lesson): every student seated moves
 * with it, each one's course must be open on the new day, and the database
 * refuses a time any of them is booked.
 */
final class RescheduleSession
{
    public function __construct(
        private readonly PolicyDecider $decider,
        private readonly RecordPolicyDecision $record,
        private readonly AvailabilityQuery $availability,
        private readonly EnrollmentApi $enrollments,
        private readonly CourseDays $days,
    ) {}

    public function handle(Session $session, CarbonImmutable $newStart, string $askedBy, ?string $actorId, bool $waive = false, ?CarbonImmutable $at = null): Session
    {
        $at ??= CarbonImmutable::now();
        if ($session->status !== SessionStatus::Scheduled || $session->starts_at->lessThanOrEqualTo($at)) {
            throw ValidationException::withMessages(['session' => __('scheduling::policy.errors.not_movable')]);
        }
        $newStart = $newStart->utc();
        $newEnd = $newStart->addMinutes($session->durationMinutes());
        if ($newStart->lessThanOrEqualTo($at)) {
            throw ValidationException::withMessages(['starts_at' => __('scheduling::policy.errors.in_the_past')]);
        }
        if ($session->isGroup() && $askedBy !== 'academy') {
            throw ValidationException::withMessages(['session' => __('scheduling::group_outcomes.errors.whole_group')]);
        }
        $courses = $session->isGroup()
            ? SessionParticipant::query()->where('session_id', $session->id)->where('role', ParticipantRole::Student->value)->where('is_active', true)->whereNotNull('enrollment_id')->pluck('enrollment_id')->all()
            : array_filter([$session->enrollment_id]);
        foreach ($courses as $enrollmentId) {
            if (($course = $this->enrollments->find($enrollmentId)) !== null) {
                $this->days->assertOpen($course, $newStart);
            }
        }

        [$decision, $input, $version] = $this->decider->decide($session, $askedBy === 'student' ? PolicyEvent::StudentReschedule : PolicyEvent::AcademyReschedule, $at);
        if (! $decision->allowed && ! $waive) {
            throw ValidationException::withMessages(['session' => __('scheduling::policy.refusals.'.$decision->refusal, ['n' => $version->policy()->get('quota_per_block'), 'hours' => $version->policy()->get('student_notice_hours')])]);
        }

        $teacher = $this->availability->forTeachers([$session->teacher_id], $newStart->subHour(), $newEnd->addHour())[$session->teacher_id] ?? null;
        $own = [$session->starts_at->getTimestamp(), $session->ends_at->getTimestamp()];
        $free = new IntervalSet(array_merge($teacher?->free->intervals ?? [], [$own]));
        if ($teacher === null || ! $teacher->shifts->contains($newStart->getTimestamp(), $newEnd->getTimestamp()) || ! $free->contains($newStart->getTimestamp(), $newEnd->getTimestamp())) {
            throw ValidationException::withMessages(['starts_at' => __('scheduling::policy.errors.teacher_busy')]);
        }

        try {
            DB::transaction(function () use ($session, $newStart, $newEnd, $askedBy, $actorId, $waive, $decision, $input, $version) {
                $old = $session->starts_at;
                $session->starts_at = $newStart;
                $session->ends_at = $newEnd;
                $session->reschedule_count++;
                $session->is_detached = true;
                $session->version++;
                $this->record->handle($session, $decision, $input, $version, ['by' => $askedBy, 'waived' => $waive, 'moved_from' => $old->toIso8601String()], $actorId);
                $session->save();

                $period = SessionParticipant::blockPeriod($newStart, $newEnd);
                foreach ([ParticipantRole::Teacher, ParticipantRole::Student] as $role) {
                    $session->participants()->where('role', $role->value)->where('is_active', true)->update(['block_period' => $period]);
                }

                SessionEvent::record($session, SessionEventKind::Rescheduled, ['from' => $old->toIso8601String(), 'to' => $newStart->toIso8601String(), 'by' => $askedBy], $actorId);
                DB::afterCommit(fn () => event(new SessionRescheduled($session->academy_id, $session->id, $old->utc()->toIso8601ZuluString(), $actorId)));
            });
        } catch (QueryException $e) {
            $session->refresh();    // rolled back: the lesson as it stands, so another time can be tried on it
            if (str_contains($e->getMessage(), 'session_participants_no_double_booking')) {
                throw ValidationException::withMessages(['starts_at' => __('scheduling::policy.errors.someone_busy')]);
            }

            throw $e;
        }

        return $session;
    }
}
