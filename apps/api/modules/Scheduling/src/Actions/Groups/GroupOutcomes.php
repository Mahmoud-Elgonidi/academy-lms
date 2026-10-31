<?php

namespace Modules\Scheduling\Actions\Groups;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Actions\Policy\PolicyDecider;
use Modules\Scheduling\Actions\Policy\RecordPolicyDecision;
use Modules\Scheduling\Actions\SettleSessionCredits;
use Modules\Scheduling\Contracts\Events\SessionCancelled;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;
use Modules\Scheduling\Domain\Models\SessionParticipant;
use Modules\Scheduling\Domain\Policy\PolicyEvent;

/**
 * Each student's outcome in a group lesson (docs/architecture/02, "الحصص
 * الجماعية"): attended, absent, excused or makeup, on their own seat, and
 * each seat's credit settled on their own account by the policy. The lesson
 * itself closes once every seat is decided: completed when someone
 * attended, student absent when nobody did, the teacher absent when every
 * seat is owed a makeup. It closes once, with one SessionFinalized whose
 * decision lists the seats (decision.seats); the teacher's pay is the
 * lesson's, never a seat's.
 */
final class GroupOutcomes
{
    public const ATTENDED = 'attended';

    public const ABSENT = 'absent';

    public const EXCUSED = 'excused';

    public const MAKEUP = 'makeup';

    /** A seat's outcome as the lesson's status it stands for (what a dispute or a review is about). */
    public const AS_STATUS = [self::ATTENDED => 'completed', self::ABSENT => 'student_absent', self::MAKEUP => 'teacher_absent'];

    public function __construct(
        private readonly SettleSessionCredits $settle,
        private readonly PolicyDecider $decider,
        private readonly RecordPolicyDecision $record,
    ) {}

    /** @return Collection<int, SessionParticipant> every student's seat (an excused one too), first joined first */
    public function seats(Session $session): Collection
    {
        return SessionParticipant::query()->where('session_id', $session->id)->where('role', ParticipantRole::Student->value)
            ->whereNotNull('enrollment_id')->orderBy('user_id')->get();
    }

    /** @return Collection<int, SessionParticipant> the seats still waiting for an outcome */
    public function undecided(Session $session): Collection
    {
        return $this->seats($session)->filter(fn (SessionParticipant $p) => $p->outcome === null && $p->is_active)->values();
    }

    /**
     * Decides seats (student id => outcome) and settles each one's credit;
     * once no seat is left undecided the lesson closes (or $status says how).
     * Called with no outcomes, it only closes a lesson whose seats are all decided;
     * $keepOpen holds it where it is (another seat's dispute still waits).
     *
     * @param  array<string, string>  $outcomes
     * @param  array<string, mixed>  $extra  merged into the lesson's decision (a review's reason and note, taught minutes)
     * @return array{closed: bool, short: int}
     */
    public function apply(Session $session, array $outcomes, ?string $actorId, string $by, ?CarbonImmutable $at = null, array $extra = [], ?SessionStatus $status = null, bool $keepOpen = false): array
    {
        $at ??= CarbonImmutable::now();
        $makeupDays = PolicyVersion::settingsInForce()->get('makeup_validity_days');

        return DB::transaction(function () use ($session, $outcomes, $actorId, $by, $at, $extra, $status, $makeupDays, $keepOpen) {
            $seats = $this->seats($session)->keyBy('user_id');
            foreach (array_keys($outcomes) as $studentId) {
                if (! $seats->has($studentId) || $seats[$studentId]->outcome === self::EXCUSED) {
                    throw ValidationException::withMessages(['student' => __('scheduling::group_outcomes.errors.no_seat')]);
                }
            }
            $before = $session->decision['seats'] ?? [];
            $shortBefore = (int) ($session->decision['credit_short'] ?? 0);
            $after = $seats->map(fn (SessionParticipant $p) => $outcomes[$p->user_id] ?? $p->outcome);
            $decided = $after->filter(fn (?string $o, string $id) => $o !== null || ! $seats[$id]->is_active)->count() === $seats->count();
            $status ??= $decided && ! $keepOpen ? self::statusOf($after->all()) : null;
            $resolution = $extra['resolution'] ?? null;
            $extra = array_filter(array_diff_key($extra, ['resolution' => true]), fn ($v) => $v !== null);

            // The lesson's own state first, so each seat's ledger entry names what the lesson became.
            $closing = $status !== null && $status !== $session->status;
            if ($closing) {
                $from = $session->status;
                $session->transitionTo($status);
                $this->lessonDecision($session, $status, $by, $actorId, $at);
                SessionEvent::record($session, SessionEventKind::StatusChanged, array_filter([
                    'from' => $from->value, 'to' => $status->value, 'by' => $by, 'resolution' => $resolution, 'note' => $extra['note'] ?? null,
                ]), $actorId, $at);
            } else {
                $session->version++;
            }
            $session->decision = array_merge($session->decision ?? [], $extra);

            $short = 0;
            $credits = [];
            foreach ($outcomes as $studentId => $outcome) {
                $seat = $seats[$studentId];
                $credit = $this->seatCredit($session, $seat, $outcome, $by, $actorId, $at);
                $seat->outcome = $outcome;
                $seat->save();
                $credits[$studentId] = $credit;
                $short += $this->settle->settleSeat($session, (string) $seat->enrollment_id, $credit, $actorId, $makeupDays);
            }

            $session->decision = array_merge($session->decision, ['seats' => $this->summary($seats->values(), $credits, $before)],
                $short + $shortBefore > 0 ? ['credit_short' => $short + $shortBefore] : []);
            $session->save();

            if ($closing && $status === SessionStatus::Cancelled) {
                $session->participants()->update(['is_active' => false]);
                DB::afterCommit(fn () => event(new SessionCancelled($session->academy_id, $session->id, 'review', true, $actorId)));
            } elseif ($closing) {
                DB::afterCommit(fn () => event(new SessionFinalized($session->academy_id, $session->id, $status->value, $session->decision)));
            }

            return ['closed' => $closing, 'short' => $short];
        });
    }

    /**
     * Rule 9 for a group: the teacher never came, so the lesson is the
     * teacher's absence and every student in it is owed a makeup.
     */
    public function teacherAbsent(Session $session, CarbonImmutable $now): void
    {
        [$decision, $input, $version] = $this->decider->decide($session, PolicyEvent::TeacherAbsent, $now);

        DB::transaction(function () use ($session, $now, $decision, $input, $version) {
            $from = $session->status;
            $session->transitionTo(SessionStatus::TeacherAbsent);
            $this->record->handle($session, $decision, $input, $version, ['by' => 'system'], null);
            $seats = $this->seats($session)->filter(fn (SessionParticipant $p) => $p->outcome !== self::EXCUSED);
            foreach ($seats as $seat) {
                $seat->outcome = self::MAKEUP;
                $seat->save();
            }
            $session->decision = $session->decision + ['seats' => $this->summary($this->seats($session), array_fill_keys($seats->pluck('user_id')->all(), 'makeup'), [])];
            $session->save();

            SessionEvent::record($session, SessionEventKind::StatusChanged, ['from' => $from->value, 'to' => SessionStatus::TeacherAbsent->value, 'by' => 'system'], null, $now);
            $this->settle->handle($session, 'makeup', null, $version->policy()->get('makeup_validity_days'));

            DB::afterCommit(fn () => event(new SessionFinalized($session->academy_id, $session->id, SessionStatus::TeacherAbsent->value, $session->decision)));
        });
    }

    /**
     * The lesson's status from its seats: completed when anyone attended,
     * the teacher's absence when every decided seat is owed a makeup,
     * otherwise the students' absence.
     *
     * @param  array<string, ?string>  $outcomes
     */
    public static function statusOf(array $outcomes): SessionStatus
    {
        $decided = array_filter($outcomes, fn (?string $o) => $o !== null && $o !== self::EXCUSED);

        return match (true) {
            in_array(self::ATTENDED, $decided, true) => SessionStatus::Completed,
            $decided !== [] && array_unique(array_values($decided)) === [self::MAKEUP] => SessionStatus::TeacherAbsent,
            default => SessionStatus::StudentAbsent,
        };
    }

    /** What a seat's outcome does to its credit: an absence by the policy (rule 5, on its own course), a lesson used, or a makeup. */
    private function seatCredit(Session $session, SessionParticipant $seat, string $outcome, string $by, ?string $actorId, CarbonImmutable $at): string
    {
        return match ($outcome) {
            self::ATTENDED => 'consumed',
            self::MAKEUP => 'makeup',
            self::ABSENT => (function () use ($session, $seat, $by, $actorId, $at) {
                [$decision, $input, $version] = $this->decider->decide($session, PolicyEvent::StudentAbsent, $at, $seat->enrollment_id);
                SessionEvent::record($session, SessionEventKind::PolicyApplied, [
                    'input' => $input->toArray(), 'decision' => $decision->toArray(), 'policy_version' => $version->version,
                    'by' => $by, 'student_id' => $seat->user_id, 'enrollment_id' => $seat->enrollment_id,
                ], $actorId, $at);

                return $decision->credit;
            })(),
            default => throw ValidationException::withMessages(['outcome' => __('scheduling::group_outcomes.errors.outcome')]),
        };
    }

    /** The lesson's decision as Payroll reads it: one group lesson, paid when taught, at the policy's share when nobody came. */
    private function lessonDecision(Session $session, SessionStatus $status, string $by, ?string $actorId, CarbonImmutable $at): void
    {
        if ($status === SessionStatus::StudentAbsent) {
            [$decision, $input, $version] = $this->decider->decide($session, PolicyEvent::StudentAbsent, $at);
            $this->record->handle($session, $decision, $input, $version, ['by' => $by], $actorId);

            return;
        }
        $session->decision = match ($status) {
            SessionStatus::Completed => ['by' => $by, 'credit' => 'consumed', 'teacher_pay_pct' => 100],
            default => ['by' => $by, 'credit' => 'makeup', 'teacher_pay_pct' => 0],
        };
    }

    /**
     * decision.seats: each student, their course, outcome and what it did to their credit.
     *
     * @param  Collection<int, SessionParticipant>  $seats
     * @param  array<string, string>  $credits  this decision's
     * @param  list<array<string, mixed>>  $before  what an earlier decision said
     * @return list<array{student_id: string, enrollment_id: ?string, outcome: ?string, credit: ?string}>
     */
    private function summary(Collection $seats, array $credits, array $before): array
    {
        $was = collect($before)->keyBy('student_id');

        return $seats->map(fn (SessionParticipant $p) => [
            'student_id' => $p->user_id,
            'enrollment_id' => $p->enrollment_id,
            'outcome' => $p->outcome,
            'credit' => $credits[$p->user_id] ?? ($was[$p->user_id]['credit'] ?? ($p->outcome === self::EXCUSED ? 'excused' : null)),
        ])->values()->all();
    }
}
