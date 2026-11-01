<?php

namespace Modules\Crm\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * What the family's own trial bookings ask of the academy's records (docs/
 * architecture/02, section 27): the lock on a number (one booking at a time
 * per number, J1 and J2 alike), the trials to come on it, and a learner's
 * history in a subject: a course that has not ended, a trial that took
 * place, trials they missed, their second trial.
 */
final class TrialFacts
{
    public function __construct(private readonly TenantContext $tenants, private readonly EnrollmentApi $enrollments) {}

    /** Held until the transaction ends: the family's bookings, one at a time per number. */
    public function lockNumber(string $phone): void
    {
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['own-trial:'.$this->tenants->id().':'.$phone]);
    }

    /** @return Collection<int, object{subject_id: string, for_child: bool, learner_name: ?string, learner_id: ?string}> the trials to come on this number */
    public function openOnNumber(string $phone): Collection
    {
        return DB::table('lead_trials as t')
            ->join('leads as l', fn ($j) => $j->on('l.academy_id', '=', 't.academy_id')->on('l.id', '=', 't.lead_id'))
            ->join('sessions as s', fn ($j) => $j->on('s.academy_id', '=', 't.academy_id')->on('s.id', '=', 't.session_id'))
            ->where('l.phone_e164', $phone)->where('t.status', 'booked')
            ->get(['s.subject_id', 'l.for_child', 'l.learner_name', 'l.learner_id']);
    }

    /** The learner's course in the subject that has not ended (waiting for payment, running or paused), if any. */
    public function course(string $learnerId, string $subjectId): ?EnrollmentStatus
    {
        foreach ($this->enrollments->forStudent($learnerId) as $course) {
            if ($course->subjectId === $subjectId && $course->status !== EnrollmentStatus::Ended) {
                return $course->status;
            }
        }

        return null;
    }

    public function studying(string $learnerId, string $subjectId): bool
    {
        return $this->course($learnerId, $subjectId) !== null;
    }

    /** A free trial in the subject took place (the learner came). */
    public function hadTrial(string $learnerId, string $subjectId): bool
    {
        return $this->trials($learnerId, $subjectId)->where('s.status', 'completed')->exists();
    }

    /** Trials in the subject the learner did not come to (a teacher's absence or the academy's cancellation is not theirs). */
    public function studentMissed(string $learnerId, string $subjectId): int
    {
        return $this->trials($learnerId, $subjectId)->where('s.status', 'student_absent')->count();
    }

    /** The learner's second trial in the subject was booked already (whatever became of it). */
    public function secondUsed(string $learnerId, string $subjectId): bool
    {
        return DB::table('lead_trials as t')
            ->join('leads as l', fn ($j) => $j->on('l.academy_id', '=', 't.academy_id')->on('l.id', '=', 't.lead_id'))
            ->join('sessions as s', fn ($j) => $j->on('s.academy_id', '=', 't.academy_id')->on('s.id', '=', 't.session_id'))
            ->where('l.learner_id', $learnerId)->where('s.subject_id', $subjectId)->where('t.kind', 'second')->exists();
    }

    /** The learner had an offer after a trial in the subject (whatever became of it). */
    public function hadOffer(string $learnerId, string $subjectId): bool
    {
        return DB::table('lead_trials as t')
            ->join('leads as l', fn ($j) => $j->on('l.academy_id', '=', 't.academy_id')->on('l.id', '=', 't.lead_id'))
            ->join('sessions as s', fn ($j) => $j->on('s.academy_id', '=', 't.academy_id')->on('s.id', '=', 't.session_id'))
            ->where('l.learner_id', $learnerId)->where('s.subject_id', $subjectId)->whereNotNull('t.offer_coupon_id')->exists();
    }

    private function trials(string $learnerId, string $subjectId)
    {
        return DB::table('sessions as s')
            ->join('session_participants as p', fn ($j) => $j->on('p.academy_id', '=', 's.academy_id')->on('p.session_id', '=', 's.id'))
            ->where('p.user_id', $learnerId)->where('p.role', 'student')->where('s.type', 'trial')->where('s.subject_id', $subjectId);
    }
}
