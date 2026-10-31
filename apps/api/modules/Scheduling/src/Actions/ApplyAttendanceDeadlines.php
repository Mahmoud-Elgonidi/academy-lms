<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Actions\Groups\GroupOutcomes;
use Modules\Scheduling\Actions\Policy\PolicyDecider;
use Modules\Scheduling\Actions\Policy\RecordPolicyDecision;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\AttendancePolicy;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\LessonChanged;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;
use Modules\Scheduling\Domain\Models\SessionParticipant;
use Modules\Scheduling\Domain\Policy\PolicyEvent;
use Modules\Scheduling\Queries\TaughtMinutes;

/**
 * The per-minute sweeper for the current academy: what the live board's
 * countdowns promise happens here.
 *
 *  - teacher never joined by T+10: teacher absent (student in) or needs review (nobody in);
 *  - teacher in, student never joined by the end of the wait: student absent;
 *  - ten minutes after the end: completed if 75% was taught, else needs review.
 *
 * A group lesson (docs/architecture/02, "الحصص الجماعية") is decided seat
 * by seat: the teacher absent makes it the teacher's absence and owes every
 * student a makeup; with the teacher in and nobody in by the end of the
 * wait, every seat is absent; otherwise at the end each student who never
 * came is absent and each who came attended when they were taught the
 * share (their own minutes with the teacher), the lesson going to review
 * when someone in it was taught less.
 */
final class ApplyAttendanceDeadlines
{
    public function __construct(
        private readonly MarkStudentAbsent $markStudentAbsent,
        private readonly FlagForReview $flagForReview,
        private readonly SettleSessionCredits $settle,
        private readonly PolicyDecider $decider,
        private readonly RecordPolicyDecision $record,
        private readonly TaughtMinutes $taught,
        private readonly GroupOutcomes $groups,
    ) {}

    /** @return int how many sessions changed */
    public function handle(?CarbonImmutable $now = null, ?AttendancePolicy $policy = null): int
    {
        $now ??= CarbonImmutable::now();
        $policy ??= PolicyVersion::settingsInForce()->attendance();
        $changed = 0;

        $open = Session::query()
            ->whereIn('status', [SessionStatus::Scheduled->value, SessionStatus::InProgress->value])
            ->where('starts_at', '<=', $now->subMinutes($policy->teacherLateAfterMin))
            ->with('participants')
            ->get();

        foreach ($open as $session) {
            try {
                $changed += $this->deadline($session, $now, $policy);
            } catch (LessonChanged) {
                continue;   // someone decided it while we ran: theirs stands
            }
        }

        return $changed;
    }

    /** @return int 1 when the lesson changed */
    private function deadline(Session $session, CarbonImmutable $now, AttendancePolicy $policy): int
    {
        if ($session->isGroup()) {
            return $this->groupDeadline($session, $now, $policy);
        }
        $teacher = $session->participant(ParticipantRole::Teacher);
        $student = $session->participant(ParticipantRole::Student);
        $teacherIn = $teacher?->first_join_at !== null;
        $studentIn = $student?->first_join_at !== null;

        if (! $teacherIn && $now->greaterThanOrEqualTo($session->starts_at->addMinutes($policy->teacherAbsentAfterMin))) {
            $studentIn ? $this->teacherAbsent($session, $now) : $this->flagForReview->handle($session, 'nobody_joined', null, null);

            return 1;
        }

        if ($teacherIn && ! $studentIn && $now->greaterThanOrEqualTo($session->starts_at->addMinutes($policy->studentWaitMin($session->durationMinutes())))) {
            $this->markStudentAbsent->handle($session, null, 'system', $now);

            return 1;
        }

        if ($teacherIn && $studentIn && $now->greaterThanOrEqualTo($session->ends_at->addMinutes($policy->finalizeAfterEndMin))) {
            // From the classroom's stretches when there are any (both in at once, inside the lesson);
            // otherwise from the first joins, as before Classroom.
            $taught = $this->taught->hasSegments($session->id)
                ? $this->taught->of($session->id, $session->starts_at, $session->ends_at, $now)
                : (int) max(0, $teacher->first_join_at->max($student->first_join_at)->max($session->starts_at)->diffInMinutes($session->ends_at, false));
            $taught >= $policy->completionShare * $session->durationMinutes()
                ? $this->complete($session, (int) $taught, $now)
                : $this->flagForReview->handle($session, 'short_delivery', null, null);

            return 1;
        }

        return 0;
    }

    /** @return int 1 when the lesson changed */
    private function groupDeadline(Session $session, CarbonImmutable $now, AttendancePolicy $policy): int
    {
        $teacher = $session->participant(ParticipantRole::Teacher);
        $seats = $session->participants->filter(fn (SessionParticipant $p) => $p->role === ParticipantRole::Student && $p->is_active && $p->outcome === null && $p->enrollment_id !== null);
        $teacherIn = $teacher?->first_join_at !== null;
        $anyIn = $seats->contains(fn (SessionParticipant $p) => $p->first_join_at !== null);

        if (! $teacherIn && $now->greaterThanOrEqualTo($session->starts_at->addMinutes($policy->teacherAbsentAfterMin))) {
            $anyIn ? $this->groups->teacherAbsent($session, $now) : $this->flagForReview->handle($session, 'nobody_joined', null, null);

            return 1;
        }

        if ($teacherIn && ! $anyIn && $now->greaterThanOrEqualTo($session->starts_at->addMinutes($policy->studentWaitMin($session->durationMinutes())))) {
            $this->groups->apply($session, $seats->mapWithKeys(fn (SessionParticipant $p) => [$p->user_id => GroupOutcomes::ABSENT])->all(), null, 'system', $now);

            return 1;
        }

        if ($teacherIn && $anyIn && $now->greaterThanOrEqualTo($session->ends_at->addMinutes($policy->finalizeAfterEndMin))) {
            $outcomes = [];
            $minutes = [];
            foreach ($seats as $seat) {
                if ($seat->first_join_at === null) {
                    $outcomes[$seat->user_id] = GroupOutcomes::ABSENT;

                    continue;
                }
                $taught = $this->taught->hasSegments($session->id, $seat->user_id)
                    ? $this->taught->of($session->id, $session->starts_at, $session->ends_at, $now, $seat->user_id)
                    : (int) max(0, $teacher->first_join_at->max($seat->first_join_at)->max($session->starts_at)->diffInMinutes($session->ends_at, false));
                if ($taught < $policy->completionShare * $session->durationMinutes()) {
                    $this->flagForReview->handle($session, 'short_delivery', null, null);

                    return 1;
                }
                $outcomes[$seat->user_id] = GroupOutcomes::ATTENDED;
                $minutes[] = (int) $taught;
            }
            $this->groups->apply($session, $outcomes, null, 'system', $now, ['taught_min' => $minutes === [] ? null : max($minutes)]);

            return 1;
        }

        return 0;
    }

    /** Rule 9: the student is owed a makeup, the teacher is not paid. */
    private function teacherAbsent(Session $session, CarbonImmutable $now): void
    {
        [$decision, $input, $version] = $this->decider->decide($session, PolicyEvent::TeacherAbsent, $now);

        DB::transaction(function () use ($session, $now, $decision, $input, $version) {
            $session->transitionTo(SessionStatus::TeacherAbsent);
            $this->record->handle($session, $decision, $input, $version, ['by' => 'system'], null);
            $session->save();

            SessionEvent::record($session, SessionEventKind::StatusChanged, ['from' => SessionStatus::Scheduled->value, 'to' => SessionStatus::TeacherAbsent->value, 'by' => 'system'], null, $now);
            $this->settle->handle($session, $decision->credit, null, $version->policy()->get('makeup_validity_days'));

            DB::afterCommit(fn () => event(new SessionFinalized($session->academy_id, $session->id, SessionStatus::TeacherAbsent->value, $session->decision)));
        });
    }

    private function complete(Session $session, int $taught, CarbonImmutable $now): void
    {
        $decision = ['by' => 'system', 'credit' => 'consumed', 'teacher_pay_pct' => 100, 'taught_min' => $taught];

        DB::transaction(function () use ($session, $decision, $now) {
            $from = $session->status;
            $session->transitionTo(SessionStatus::Completed);
            $session->decision = $decision;
            $session->save();

            SessionEvent::record($session, SessionEventKind::StatusChanged, ['from' => $from->value, 'to' => SessionStatus::Completed->value, 'by' => 'system'], null, $now);
            $this->settle->handle($session, 'consumed');

            DB::afterCommit(fn () => event(new SessionFinalized($session->academy_id, $session->id, SessionStatus::Completed->value, $decision)));
        });
    }
}
