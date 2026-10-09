<?php

namespace Modules\Scheduling\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Support\MinorsGuard;

/**
 * Who can cover a lesson (docs/architecture/01, flow "ز": the same engine as
 * finding a teacher): active teachers of the subject, on shift and free for
 * the whole lesson (their own time off and the academy's holidays already
 * out), other than its teacher, and for a student under 18 only teachers
 * cleared for minors. Those who already taught the student come
 * first, then the least busy.
 */
final class SubstituteCandidates
{
    public function __construct(
        private readonly AvailabilityQuery $availability,
        private readonly MinorsGuard $minors,
        private readonly PeopleApi $people,
    ) {}

    /**
     * @param  iterable<Session>  $sessions
     * @return array<string, list<array{id: string, name: string, timezone: string, local: string, taught: int}>> by session id
     */
    public function forSessions(iterable $sessions, ?string $viewerId = null, int $limit = 6): array
    {
        $sessions = collect($sessions);
        if ($sessions->isEmpty()) {
            return [];
        }

        $teachers = DB::table('teacher_profiles as tp')
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'tp.academy_id')->on('u.id', '=', 'tp.user_id'))
            ->join('teacher_subjects as ts', fn ($j) => $j->on('ts.academy_id', '=', 'tp.academy_id')->on('ts.teacher_id', '=', 'tp.user_id'))
            ->whereIn('ts.subject_id', $sessions->pluck('subject_id')->unique()->values())
            ->where('tp.status', 'active')->where('u.status', 'active')
            ->get(['u.id', 'u.name', 'u.timezone', 'ts.subject_id']);
        if ($teachers->isEmpty()) {
            return $sessions->mapWithKeys(fn (Session $s) => [$s->id => []])->all();
        }

        $from = CarbonImmutable::instance($sessions->min('starts_at'));
        $to = CarbonImmutable::instance($sessions->max('ends_at'));
        $free = $this->availability->forTeachers($teachers->pluck('id')->unique()->values()->all(), $from, $to, $viewerId);

        $students = DB::table('session_participants')->whereIn('session_id', $sessions->pluck('id'))->where('role', 'student')->pluck('user_id', 'session_id');
        // A lesson with a student under 18 goes only to a teacher cleared for minors (safeguarding).
        $minors = $this->people->minors($students->unique()->values()->all());
        $cleared = in_array(true, $minors, true) ? array_flip($this->minors->cleared($teachers->pluck('id')->unique()->values()->all())) : null;
        $taught = DB::table('sessions as s')
            ->join('session_participants as p', fn ($j) => $j->on('p.academy_id', '=', 's.academy_id')->on('p.session_id', '=', 's.id')->where('p.role', '=', 'student'))
            ->whereIn('p.user_id', $students->unique()->values())->where('s.status', 'completed')
            ->groupBy('s.teacher_id', 'p.user_id')
            ->selectRaw('s.teacher_id, p.user_id, count(*) as n')
            ->get()->mapWithKeys(fn ($r) => ["{$r->teacher_id}:{$r->user_id}" => (int) $r->n]);

        $result = [];
        foreach ($sessions as $session) {
            /** @var Session $session */
            $student = $students[$session->id] ?? null;
            $result[$session->id] = $teachers
                ->where('subject_id', $session->subject_id)
                ->reject(fn ($t) => $t->id === $session->teacher_id)
                ->reject(fn ($t) => $cleared !== null && ($minors[$student] ?? false) && ! isset($cleared[$t->id]))
                ->filter(fn ($t) => isset($free[$t->id]) && $free[$t->id]->free->contains($session->starts_at->getTimestamp(), $session->ends_at->getTimestamp()))
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'timezone' => $t->timezone ?? 'UTC',
                    'local' => $session->starts_at->setTimezone($t->timezone ?? 'UTC')->format('H:i'),
                    'taught' => $taught["{$t->id}:{$student}"] ?? 0,
                    'load' => $free[$t->id]->utilisation(),
                ])
                ->sortBy([['taught', 'desc'], ['load', 'asc'], ['name', 'asc']])
                ->take($limit)
                ->map(fn (array $c) => array_diff_key($c, ['load' => true]))
                ->values()->all();
        }

        return $result;
    }
}
