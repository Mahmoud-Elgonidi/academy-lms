<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\AttendancePolicy;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;

/**
 * The per-minute sweeper for the current academy: what the live board's
 * countdowns promise happens here.
 *
 *  - teacher never joined by T+10: teacher absent (student in) or needs review (nobody in);
 *  - teacher in, student never joined by the end of the wait: student absent;
 *  - ten minutes after the end: completed if 75% was taught, else needs review.
 */
final class ApplyAttendanceDeadlines
{
    public function __construct(
        private readonly MarkStudentAbsent $markStudentAbsent,
        private readonly FlagForReview $flagForReview,
        private readonly SettleSessionCredits $settle,
    ) {}

    /** @return int how many sessions changed */
    public function handle(?CarbonImmutable $now = null, ?AttendancePolicy $policy = null): int
    {
        $now ??= CarbonImmutable::now();
        $policy ??= AttendancePolicy::defaults();
        $changed = 0;

        $open = Session::query()
            ->whereIn('status', [SessionStatus::Scheduled->value, SessionStatus::InProgress->value])
            ->where('starts_at', '<=', $now->subMinutes($policy->teacherLateAfterMin))
            ->with('participants')
            ->get();

        foreach ($open as $session) {
            $teacher = $session->participant(ParticipantRole::Teacher);
            $student = $session->participant(ParticipantRole::Student);
            $teacherIn = $teacher?->first_join_at !== null;
            $studentIn = $student?->first_join_at !== null;

            if (! $teacherIn && $now->greaterThanOrEqualTo($session->starts_at->addMinutes($policy->teacherAbsentAfterMin))) {
                $studentIn ? $this->teacherAbsent($session, $now) : $this->flagForReview->handle($session, 'nobody_joined', null, null);
                $changed++;

                continue;
            }

            if ($teacherIn && ! $studentIn && $now->greaterThanOrEqualTo($session->starts_at->addMinutes($policy->studentWaitMin($session->durationMinutes())))) {
                $this->markStudentAbsent->handle($session, null, 'system', $now);
                $changed++;

                continue;
            }

            if ($teacherIn && $studentIn && $now->greaterThanOrEqualTo($session->ends_at->addMinutes($policy->finalizeAfterEndMin))) {
                $from = $teacher->first_join_at->max($student->first_join_at)->max($session->starts_at);
                $taught = max(0, $from->diffInMinutes($session->ends_at, false));
                $taught >= $policy->completionShare * $session->durationMinutes()
                    ? $this->complete($session, (int) $taught, $now)
                    : $this->flagForReview->handle($session, 'short_delivery', null, null);
                $changed++;
            }
        }

        return $changed;
    }

    private function teacherAbsent(Session $session, CarbonImmutable $now): void
    {
        $this->close($session, SessionStatus::TeacherAbsent, ['by' => 'system', 'rule' => 9, 'credit' => 'makeup', 'teacher_pay' => 'none'], $now);
    }

    private function complete(Session $session, int $taught, CarbonImmutable $now): void
    {
        $this->close($session, SessionStatus::Completed, ['by' => 'system', 'credit' => 'consumed', 'taught_min' => $taught], $now);
    }

    /** @param  array<string, mixed>  $decision */
    private function close(Session $session, SessionStatus $to, array $decision, CarbonImmutable $now): void
    {
        DB::transaction(function () use ($session, $to, $decision, $now) {
            $from = $session->status;
            $session->transitionTo($to);
            $session->decision = $decision;
            $session->save();

            SessionEvent::record($session, SessionEventKind::StatusChanged, ['from' => $from->value, 'to' => $to->value, 'by' => 'system'], null, $now);
            $this->settle->handle($session, $decision['credit']);

            DB::afterCommit(fn () => event(new SessionFinalized($session->academy_id, $session->id, $to->value, $decision)));
        });
    }
}
