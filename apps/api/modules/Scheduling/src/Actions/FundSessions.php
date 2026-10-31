<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Scheduling\Actions\Groups\GroupSeats;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;
use Modules\Scheduling\Domain\Models\SessionParticipant;

/**
 * Pays for an enrollment's unpaid upcoming sessions from its credit, in
 * date order (docs/architecture/02, "حالة التمويل"): the first session that
 * cannot be covered stops the run, so credit is never spent on a later
 * lesson while an earlier one waits. On a shared package that order runs
 * across every course drawing on the balance. A group lesson is paid for
 * seat by seat: the course's student's own seat in it (their participant
 * row), from their own account, in the same date order.
 */
final class FundSessions
{
    public function __construct(
        private readonly CreditsApi $credits,
        private readonly EnrollmentApi $enrollments,
        private readonly GroupSeats $seats,
    ) {}

    /** @return int how many sessions were funded */
    public function handle(string $enrollmentId, ?CarbonImmutable $now = null): int
    {
        $accountId = $this->enrollments->find($enrollmentId)?->creditAccountId;
        if ($accountId === null) {
            return 0;
        }

        $courses = array_map(fn ($e) => $e->id, $this->enrollments->forCreditAccount($accountId)) ?: [$enrollmentId];
        $now ??= CarbonImmutable::now();
        $waiting = Session::query()
            ->whereIn('enrollment_id', $courses)
            ->where('status', SessionStatus::Scheduled->value)
            ->where('funding_status', FundingStatus::Unfunded->value)
            ->where('starts_at', '>', $now)
            ->get()
            ->map(fn (Session $s) => ['at' => $s->starts_at, 'session' => $s, 'seat' => null]);
        $seats = SessionParticipant::query()
            ->join('sessions as s', fn ($j) => $j->on('s.academy_id', '=', 'session_participants.academy_id')->on('s.id', '=', 'session_participants.session_id'))
            ->whereIn('session_participants.enrollment_id', $courses)
            ->where('session_participants.role', ParticipantRole::Student->value)
            ->where('session_participants.funding_status', FundingStatus::Unfunded->value)
            ->whereNotNull('s.group_id')->where('s.status', SessionStatus::Scheduled->value)->where('s.starts_at', '>', $now)
            ->get(['session_participants.session_id', 'session_participants.user_id', 's.starts_at as lesson_at'])
            ->map(fn ($p) => ['at' => CarbonImmutable::parse($p->getAttribute('lesson_at')), 'session' => null, 'seat' => $p]);

        $funded = 0;
        foreach ($waiting->concat($seats)->sortBy(fn (array $item) => $item['at']->getTimestamp())->values() as $item) {
            $ok = $item['seat'] !== null
                ? $this->fundSeat($item['seat']->session_id, $item['seat']->user_id, $accountId)
                : $this->fundLesson($item['session'], $accountId);
            if ($ok === null) {
                continue;
            }
            if (! $ok) {
                break;
            }
            $funded++;
        }

        return $funded;
    }

    /** @return ?bool funded, not enough credit, or nothing to do (paid for or cancelled meanwhile) */
    private function fundLesson(Session $session, string $accountId): ?bool
    {
        return DB::transaction(function () use ($session, $accountId) {
            // Read again under the lesson's lock: one cancelled or paid for meanwhile is skipped, never paid for twice
            // or held for nothing; one moved meanwhile is paid for at its new time.
            $session = Session::query()->whereKey($session->id)->lock('for no key update')->first();
            if ($session === null || $session->status !== SessionStatus::Scheduled || $session->funding_status !== FundingStatus::Unfunded) {
                return null;
            }
            if (! $this->credits->reserve($session->id, $accountId, $session->durationMinutes(), $session->starts_at, "session:{$session->id}:reserve")) {
                return false;
            }
            $session->funding_status = FundingStatus::Funded;
            $session->save();
            SessionEvent::record($session, SessionEventKind::DecisionLogged, ['decision' => 'funded']);

            return true;
        });
    }

    /** One student's seat in a group lesson, from their own account. */
    private function fundSeat(string $sessionId, string $studentId, string $accountId): ?bool
    {
        return DB::transaction(function () use ($sessionId, $studentId, $accountId) {
            $session = Session::query()->whereKey($sessionId)->lock('for no key update')->first();
            $seat = SessionParticipant::query()->where('session_id', $sessionId)->where('user_id', $studentId)->first();
            if ($session === null || $seat === null || $session->status !== SessionStatus::Scheduled || $seat->funding_status !== FundingStatus::Unfunded->value) {
                return null;
            }
            if (! $this->credits->reserveFor($session->id, $accountId, $session->durationMinutes(), $session->starts_at, $this->seats->reserveKey($session->id, $accountId, $studentId))) {
                return false;
            }
            SessionParticipant::query()->where('session_id', $sessionId)->where('user_id', $studentId)->update(['funding_status' => FundingStatus::Funded->value]);
            $this->seats->syncFunding($session);
            SessionEvent::record($session, SessionEventKind::DecisionLogged, ['decision' => 'funded', 'user_id' => $studentId]);

            return true;
        });
    }
}
