<?php

namespace Modules\Scheduling\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Contracts\DTOs\TeacherSearch;
use Modules\Scheduling\Domain\Availability\Candidate;
use Modules\Scheduling\Domain\Availability\Matcher;

/**
 * "Find a teacher" (docs/architecture/02, section 2): hard filters in SQL,
 * then free time and matching per teacher, then a weighted score.
 *
 * Score (of the weights in the docs, the ones we can measure today):
 * availability 35, load balance 15, soft preferences 10, scaled to 100.
 * Teacher tier, continuity and trial conversion join when Evaluation lands.
 */
final class FindTeachersQuery
{
    public function __construct(
        private readonly AvailabilityQuery $availability,
        private readonly Matcher $matcher,
    ) {}

    /**
     * @return list<array{teacher: object, score: int, utilisation: float, candidates: list<Candidate>,
     *     chips: array<int, list<Candidate>>, pattern: ?list<Candidate>}>
     */
    public function run(TeacherSearch $search): array
    {
        $pref = $search->preference;

        $teachers = DB::table('teacher_profiles as tp')
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'tp.academy_id')->on('u.id', '=', 'tp.user_id'))
            ->join('teacher_subjects as ts', fn ($j) => $j->on('ts.academy_id', '=', 'tp.academy_id')->on('ts.teacher_id', '=', 'tp.user_id'))
            ->where('ts.subject_id', $search->subjectId)
            ->where('tp.status', 'active')
            ->where('u.status', 'active')
            ->where($search->forTrial ? 'tp.accepts_trials' : 'tp.accepts_new', true)
            ->when($search->gender === 'female_only', fn ($q) => $q->where('tp.gender', 'female'))
            ->when($search->gender === 'male_only', fn ($q) => $q->where('tp.gender', 'male'))
            ->when($search->language, fn ($q, $lang) => $q->whereRaw('tp.languages @> ?::jsonb', [json_encode([$lang])]))
            ->select('u.id', 'u.name', 'u.timezone', 'tp.gender', 'tp.languages', 'tp.dialect')
            ->get()
            ->keyBy('id');

        if ($teachers->isEmpty()) {
            return [];
        }

        $from = CarbonImmutable::parse($pref->startDate->toDateString(), $pref->timezone)->utc();
        $free = $this->availability->forTeachers($teachers->keys()->all(), $from, $from->addWeeks($pref->weeks), $search->searcherId);

        $results = [];
        foreach ($free as $teacherId => $availability) {
            $candidates = $this->matcher->candidates($availability, $pref);
            if ($candidates === []) {
                continue;
            }
            $pattern = $this->matcher->suggest($candidates, $pref->perWeek);
            $teacher = $teachers[$teacherId];
            $teacher->languages = json_decode($teacher->languages, true) ?: [];

            $coverage = $pattern
                ? array_sum(array_map(fn (Candidate $c) => $c->coverage, $pattern)) / (count($pattern) * $pref->weeks)
                : max(array_map(fn (Candidate $c) => $c->coverage, $candidates)) / $pref->weeks / 2;
            $soft = match ($search->gender) {
                'prefer_female' => $teacher->gender === 'female' ? 1.0 : 0.0,
                'prefer_male' => $teacher->gender === 'male' ? 1.0 : 0.0,
                default => 1.0,
            };

            $results[] = [
                'teacher' => $teacher,
                'score' => (int) round(100 * (0.35 * $coverage + 0.15 * (1 - $availability->utilisation()) + 0.10 * $soft) / 0.60),
                'utilisation' => $availability->utilisation(),
                'candidates' => $candidates,
                'chips' => $this->matcher->chips($candidates),
                'pattern' => $pattern,
            ];
        }

        usort($results, fn (array $a, array $b) => [$b['score'], $a['teacher']->name] <=> [$a['score'], $b['teacher']->name]);

        return $results;
    }
}
