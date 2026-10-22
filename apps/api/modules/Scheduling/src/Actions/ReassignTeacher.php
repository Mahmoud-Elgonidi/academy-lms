<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\RecurringSlot;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;
use Modules\Scheduling\Domain\Models\SessionParticipant;
use Modules\Scheduling\Domain\Slots\WeeklyTime;
use Modules\Scheduling\Queries\AvailabilityQuery;
use Modules\Scheduling\Support\CourseDays;
use Modules\Scheduling\Support\MinorsGuard;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\LocalTimeResolver;

/**
 * Moves an enrollment to another teacher from a date (docs/architecture/02,
 * section 3, "تغيير المدرس"): the student keeps the same times. The new
 * teacher must be on shift and free for every booked session from then; the
 * weekly times move to the new teacher's clock, and the booked sessions move
 * with their credit, re-timed on that clock (so a lesson after one country's
 * summer-time change can shift by an hour, and the result says how many).
 * The old weekly times end the day before. An ended course keeps its last
 * teacher; one set to end has its new weekly times end on that day too.
 */
final class ReassignTeacher
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly AvailabilityQuery $availability,
        private readonly CreateRecurringSlots $slots,
        private readonly MaterializeSessions $materialize,
        private readonly FundSessions $fund,
        private readonly LocalTimeResolver $resolver,
        private readonly MinorsGuard $minors,
        private readonly CourseDays $days,
    ) {}

    /**
     * @return array{moved: int, retimed: int} booked sessions moved, and how many of
     *                                         them changed time because the two teachers' clocks differ (summer time)
     */
    public function handle(string $enrollmentId, string $teacherId, CarbonImmutable $from, ?string $actorId): array
    {
        $enrollment = $this->enrollments->find($enrollmentId) ?? throw ValidationException::withMessages(['enrollment' => __('scheduling::policy.errors.no_enrollment')]);
        if ($enrollment->status === EnrollmentStatus::Ended) {
            throw ValidationException::withMessages(['enrollment' => __('scheduling::courses.errors.ended')]);
        }
        if ($enrollment->teacherId === $teacherId) {
            throw ValidationException::withMessages(['teacher' => __('scheduling::policy.errors.same_teacher')]);
        }
        $this->minors->assert($teacherId, $enrollment->studentId);
        $newTz = DB::table('users')->where('id', $teacherId)->value('timezone') ?? 'UTC';
        $fromUtc = CarbonImmutable::parse($from->toDateString(), $newTz)->utc()->max(CarbonImmutable::now());

        $sessions = Session::query()->where('enrollment_id', $enrollmentId)->where('status', SessionStatus::Scheduled->value)
            ->where('starts_at', '>=', $fromUtc)->orderBy('starts_at')->get();
        $oldSlots = RecurringSlot::query()->where('enrollment_id', $enrollmentId)->where('status', 'active')->get();

        // Each weekly time read on the new teacher's clock, from its first moved lesson.
        $times = [];
        foreach ($oldSlots as $slot) {
            $first = $sessions->first(fn (Session $s) => $s->slot_id === $slot->id && ! $s->is_detached)?->starts_at
                ?? $this->nextOccurrence($slot, $fromUtc);
            $local = $first->setTimezone($newTz);
            $time = WeeklyTime::at($local->dayOfWeekIso, $local->format('H:i'), $slot->duration_min);
            $times[$slot->id] = ['time' => $time, 'from' => $local->startOfDay()]
                // A course set to end: the new weekly times end with it.
                + ($enrollment->endsOn === null ? [] : ['until' => CarbonImmutable::parse($this->days->slotUntil($this->days->startOf($enrollment->endsOn), $time->clock(), $newTz))]);
        }

        // Lessons keep their date and follow the new teacher's clock (docs/architecture/02, section 1).
        $plan = $sessions->map(function (Session $s) use ($times, $newTz) {
            $start = $s->starts_at;
            if (! $s->is_detached && isset($times[$s->slot_id])) {
                $start = $this->resolver->resolve($s->starts_at->setTimezone($newTz)->toDateString(), $times[$s->slot_id]['time']->clock(), $newTz)->utc;
            }

            return ['session' => $s, 'start' => $start, 'end' => $start->addMinutes($s->durationMinutes())];
        });

        if ($plan->isNotEmpty()) {
            $free = $this->availability->forTeachers([$teacherId], $plan->min('start'), $plan->max('end'))[$teacherId]->free ?? null;
            $busy = $plan->filter(fn (array $p) => $free === null || ! $free->contains($p['start']->getTimestamp(), $p['end']->getTimestamp()));
            if ($busy->isNotEmpty()) {
                throw ValidationException::withMessages(['teacher' => __('scheduling::policy.errors.new_teacher_busy', [
                    'dates' => $busy->take(6)->map(fn (array $p) => $p['start']->setTimezone($newTz)->locale(app()->getLocale())->translatedFormat(Clock::pattern('Y-m-d H:i')))->implode('، '),
                ])]);
            }
        }

        try {
            return DB::transaction(function () use ($enrollment, $teacherId, $from, $fromUtc, $newTz, $plan, $times, $oldSlots, $actorId) {
                // The old weekly times end the day before, on their own clock.
                foreach ($oldSlots as $slot) {
                    $until = $fromUtc->setTimezone($slot->anchor_tz)->toDateString();
                    DB::update(
                        "update recurring_slots set status = 'ended', updated_at = now(), effective = case when ?::date > lower(effective) then daterange(lower(effective), ?::date) else 'empty'::daterange end where id = ?",
                        [$until, $until, $slot->id],
                    );
                }

                $created = $this->slots->handle($enrollment->id, $teacherId, $enrollment->studentId, $newTz, array_values($times), $actorId);
                $map = array_combine(array_keys($times), array_map(fn (RecurringSlot $s) => $s->id, $created));

                $retimed = 0;
                foreach ($plan as ['session' => $session, 'start' => $start, 'end' => $end]) {
                    /** @var Session $session */
                    $previous = $session->teacher_id;
                    $moved = ! $start->equalTo($session->starts_at);
                    $session->teacher_id = $teacherId;
                    $session->anchor_tz = $newTz;
                    $session->starts_at = $start;
                    $session->ends_at = $end;
                    if ($session->slot_id !== null && isset($map[$session->slot_id])) {
                        $date = $session->is_detached ? substr((string) $session->occurrence_key, -10) : $start->setTimezone($newTz)->toDateString();
                        $session->slot_id = $map[$session->slot_id];
                        $session->occurrence_key = "{$session->slot_id}:{$date}";
                    }
                    $session->version++;
                    $session->save();

                    $session->participants()->where('role', ParticipantRole::Teacher->value)->update(['user_id' => $teacherId]);
                    if ($moved) {
                        $session->participants()->where('is_active', true)->update(['block_period' => SessionParticipant::blockPeriod($start, $end)]);
                        $retimed++;
                    }
                    SessionEvent::record($session, SessionEventKind::TeacherChanged, array_filter(['from' => $previous, 'to' => $teacherId, 'retimed' => $moved ?: null]), $actorId);
                }

                $this->enrollments->reassignTeacher($enrollment->id, $teacherId, $from, $actorId);
                $this->materialize->handle([$enrollment->id], actorId: $actorId);
                $this->fund->handle($enrollment->id);
                app(AuditTrail::class)->record('enrollment.teacher_changed', 'enrollment', $enrollment->id, ['from' => $enrollment->teacherId, 'to' => $teacherId, 'starting' => $from->toDateString(), 'lessons_moved' => $plan->count()]);

                return ['moved' => $plan->count(), 'retimed' => $retimed];
            });
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'session_participants_no_double_booking') || str_contains($e->getMessage(), 'recurring_slots_no_overlap')) {
                throw ValidationException::withMessages(['teacher' => __('scheduling::policy.errors.someone_busy')]);
            }

            throw $e;
        }
    }

    private function nextOccurrence(RecurringSlot $slot, CarbonImmutable $from): CarbonImmutable
    {
        $date = $slot->weeklyTime()->datesBetween($from->setTimezone($slot->anchor_tz)->startOfDay(), $from->setTimezone($slot->anchor_tz)->addWeek())[0];

        return $this->resolver->resolve($date, $slot->weeklyTime()->clock(), $slot->anchor_tz)->utc;
    }
}
