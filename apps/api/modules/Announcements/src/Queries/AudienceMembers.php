<?php

namespace Modules\Announcements\Queries;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Announcements\Domain\Audience;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\People\Contracts\DTOs\ConsentState;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;

/**
 * Who an announcement reaches, at the moment it is asked (docs/architecture/02,
 * section 20): the one reckoning behind both the count the screen shows
 * before sending and the list SendAnnouncement sends to. Active accounts
 * only. Guardians and teachers by their role; students by theirs, without a
 * child the academy manages under a guardian unless students were chosen
 * outright, and never a child still waiting for a guardian's consent
 * (People's ConsentRules), nor one of another stage when a stage is chosen
 * (People's LearnerStage, "adult" being the classic look). A subject's
 * families: the students of its courses that have not ended (waiting for
 * payment, running or paused) and their guardians. It reads the people's
 * and courses' tables (reading across modules is allowed); stages and
 * consent come from People.
 */
final class AudienceMembers
{
    public function __construct(private readonly PeopleApi $people) {}

    public function count(Audience $audience): int
    {
        return count($this->resolve($audience));
    }

    /** @return list<string> user ids, each once, in a stable order */
    public function resolve(Audience $audience): array
    {
        if ($audience->forTeachers()) {
            return $this->withRole('teacher')->pluck('u.id')->sort()->values()->all();
        }

        $students = match ($audience->kind) {
            Audience::SUBJECT => $this->withRole('student')->whereIn('u.id', $this->subjectStudents((string) $audience->subjectId)),
            default => $this->withRole('student'),
        };
        $guardians = match ($audience->kind) {
            Audience::SUBJECT => $this->withRole('guardian')->whereIn('u.id', DB::table('guardian_student as gs')
                ->whereColumn('gs.academy_id', 'u.academy_id')->whereIn('gs.student_id', $this->subjectStudents((string) $audience->subjectId))->select('gs.guardian_id')),
            default => $this->withRole('guardian'),
        };

        $ids = [
            ...($audience->reachesGuardians() ? $guardians->pluck('u.id')->all() : []),
            ...($audience->reachesStudents() ? $this->learners($students, $audience) : []),
        ];
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /**
     * The students of this lot the announcement may reach themselves.
     *
     * @return list<string>
     */
    private function learners(Builder $students, Audience $audience): array
    {
        $rows = $students->get(['u.id', 'u.is_managed_minor']);
        if (! $audience->reachesManagedChildren()) {
            $rows = $rows->reject(fn (object $s) => (bool) $s->is_managed_minor);
        }
        $ids = $rows->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        $waiting = array_keys(array_filter($this->people->consentStates($ids), fn (ConsentState $c) => $c->waiting()));
        $ids = array_values(array_diff($ids, $waiting));
        if ($audience->stage !== null && $ids !== []) {
            $wanted = $audience->stage === 'adult' ? LearnerStage::Classic : LearnerStage::from($audience->stage);
            $ids = array_keys(array_filter($this->people->stages($ids), fn (LearnerStage $s) => $s === $wanted));
        }

        return $ids;
    }

    /** Active accounts holding this role. */
    private function withRole(string $role): Builder
    {
        return DB::table('users as u')->where('u.status', 'active')
            ->whereExists(fn (Builder $q) => $q->from('user_roles as r')->whereColumn('r.academy_id', 'u.academy_id')->whereColumn('r.user_id', 'u.id')->where('r.role', $role));
    }

    /** The students of a subject's courses that have not ended. */
    private function subjectStudents(string $subjectId): Builder
    {
        $open = array_map(fn (EnrollmentStatus $s) => $s->value, array_filter(EnrollmentStatus::cases(), fn (EnrollmentStatus $s) => $s !== EnrollmentStatus::Ended));

        return DB::table('enrollments as e')->where('e.subject_id', $subjectId)->whereIn('e.status', array_values($open))->select('e.student_id');
    }
}
