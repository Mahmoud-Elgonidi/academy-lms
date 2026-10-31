<?php

namespace Modules\Scheduling\Actions\Groups;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Credits\Contracts\ReleaseMode;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\GroupMember;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;
use Modules\Scheduling\Domain\Models\SessionParticipant;
use Modules\Scheduling\Support\CourseDays;

/**
 * A student's seat in a group's lessons (docs/architecture/02, "الحصص
 * الجماعية"): seated in the group's lessons while they are in it, taken out
 * of their own lessons only when they leave or pause, never touching the
 * others' seats or the lesson itself. Each seat is the student's own
 * participant row (their course, their funding), and each one taken out
 * gives back what was set aside for it from their own account. A seat taken
 * again after a pause is reserved afresh under a key of its own (the
 * number of times the student was taken out of that lesson).
 */
final class GroupSeats
{
    public function __construct(
        private readonly CreditsApi $credits,
        private readonly EnrollmentApi $enrollments,
        private readonly CourseDays $days,
    ) {}

    /** Seats a student in one group lesson; false when they are booked elsewhere then (that lesson goes on without them). */
    public function seat(Session $session, string $studentId, string $enrollmentId, ?string $actorId, FundingStatus $funding = FundingStatus::Unfunded): bool
    {
        try {
            DB::transaction(function () use ($session, $studentId, $enrollmentId, $actorId, $funding) {
                SessionParticipant::query()->create([
                    'session_id' => $session->id,
                    'user_id' => $studentId,
                    'role' => ParticipantRole::Student->value,
                    'enrollment_id' => $enrollmentId,
                    'funding_status' => $funding->value,
                    'block_period' => SessionParticipant::blockPeriod($session->starts_at, $session->ends_at),
                ]);
                SessionEvent::record($session, SessionEventKind::DecisionLogged, ['decision' => 'student_added', 'user_id' => $studentId], $actorId);
                $this->syncFunding($session);
            });

            return true;
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'session_participants_no_double_booking') || str_contains($e->getMessage(), 'session_participants_pkey')) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Seats a member in their group's booked lessons from $from (never before
     * now) up to $until, leaving out the days their course has none (a pause,
     * its end) and lessons they are already in.
     *
     * @return int lessons they were seated in
     */
    public function seatFrom(GroupMember $member, CarbonImmutable $from, ?CarbonImmutable $until, ?string $actorId): int
    {
        $course = $this->enrollments->find($member->enrollment_id);
        if ($course === null || ! $course->status->makesLessons()) {
            return 0;
        }
        $start = $from->max($this->days->startOf($member->joined_on))->max(CarbonImmutable::now());
        $end = $member->left_on === null ? $until : ($until ?? $this->days->startOf($member->left_on))->min($this->days->startOf($member->left_on));

        $lessons = Session::query()->where('group_id', $member->group_id)->where('status', SessionStatus::Scheduled->value)
            ->where('starts_at', '>', $start)->when($end !== null, fn ($q) => $q->where('starts_at', '<', $end))
            ->whereNotExists(fn ($q) => $q->from('session_participants as p')->whereColumn('p.academy_id', 'sessions.academy_id')
                ->whereColumn('p.session_id', 'sessions.id')->where('p.user_id', $member->student_id))
            ->orderBy('starts_at')->get();

        $seated = 0;
        foreach ($lessons as $lesson) {
            if ($this->days->barredFor($course, $lesson->starts_at) === null && $this->seat($lesson, $member->student_id, $member->enrollment_id, $actorId)) {
                $seated++;
            }
        }

        return $seated;
    }

    /**
     * Takes a course's student out of their own group lessons from $from
     * (never before now) up to $until: what was set aside for each comes back
     * to their balance as it was; the lesson stays for everyone else.
     *
     * @return int lessons they were taken out of
     */
    public function unseatFrom(string $enrollmentId, CarbonImmutable $from, ?CarbonImmutable $until, ?string $actorId, string $reason = 'left_group'): int
    {
        $accountId = $this->enrollments->find($enrollmentId)?->creditAccountId;
        $seats = SessionParticipant::query()->where('session_participants.enrollment_id', $enrollmentId)
            ->where('session_participants.role', ParticipantRole::Student->value)
            ->join('sessions as s', fn ($j) => $j->on('s.academy_id', '=', 'session_participants.academy_id')->on('s.id', '=', 'session_participants.session_id'))
            ->whereNotNull('s.group_id')->where('s.status', SessionStatus::Scheduled->value)
            ->where('s.starts_at', '>=', $from->max(CarbonImmutable::now()))->when($until !== null, fn ($q) => $q->where('s.starts_at', '<', $until))
            ->orderBy('s.starts_at')->get(['session_participants.session_id', 'session_participants.user_id']);

        $removed = 0;
        foreach ($seats as $seat) {
            DB::transaction(function () use ($seat, $accountId, $actorId, $reason, &$removed) {
                $session = Session::query()->whereKey($seat->session_id)->lock('for no key update')->first();
                if ($session === null || $session->status !== SessionStatus::Scheduled) {
                    return;
                }
                $n = $this->removals($session->id, $seat->user_id);
                if ($accountId !== null && $this->credits->isReservedFor($session->id, $accountId)) {
                    $this->credits->releaseFor($session->id, $accountId, ReleaseMode::Refund, "session:{$session->id}:account:{$accountId}:release:left:{$n}", $actorId, $reason);
                }
                SessionParticipant::query()->where('session_id', $session->id)->where('user_id', $seat->user_id)->delete();
                SessionEvent::record($session, SessionEventKind::DecisionLogged, ['decision' => 'student_removed', 'user_id' => $seat->user_id, 'reason' => $reason], $actorId);
                $this->syncFunding($session);
                $removed++;
            });
        }

        return $removed;
    }

    /** A group lesson counts as paid for when every student seated in it is (on their own credit, or makeup credit). */
    public function syncFunding(Session $session): void
    {
        $students = SessionParticipant::query()->where('session_id', $session->id)->where('role', ParticipantRole::Student->value)->where('is_active', true)->pluck('funding_status');
        $paid = $students->isNotEmpty() && $students->every(fn (?string $f) => in_array($f, [FundingStatus::Funded->value, FundingStatus::Makeup->value], true));
        $status = $paid ? FundingStatus::Funded : FundingStatus::Unfunded;
        if ($session->funding_status !== $status) {
            Session::query()->whereKey($session->id)->update(['funding_status' => $status->value]);
            $session->funding_status = $status;
        }
    }

    /** How many times a student was taken out of this lesson (each seat taken again is reserved under a key of its own). */
    public function removals(string $sessionId, string $studentId): int
    {
        return SessionEvent::query()->where('session_id', $sessionId)->where('kind', SessionEventKind::DecisionLogged->value)
            ->whereRaw("data->>'decision' = 'student_removed'")->whereRaw("data->>'user_id' = ?", [$studentId])->count();
    }

    /** The key a student's seat in a lesson is reserved under. */
    public function reserveKey(string $sessionId, string $accountId, string $studentId): string
    {
        $n = $this->removals($sessionId, $studentId);

        return "session:{$sessionId}:account:{$accountId}:reserve".($n === 0 ? '' : ":{$n}");
    }
}
