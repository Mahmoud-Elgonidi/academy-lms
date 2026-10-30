<?php

namespace Modules\Scheduling\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Scheduling\Contracts\DTOs\TeacherSearch;
use Modules\Scheduling\Contracts\TrialConversionRates;
use Modules\Scheduling\Domain\Availability\Candidate;
use Modules\Scheduling\Domain\Availability\ExactCheck;
use Modules\Scheduling\Domain\Availability\Matcher;
use Modules\Scheduling\Domain\Availability\TeacherAvailability;
use Modules\Scheduling\Support\MinorsGuard;

/**
 * "Find a teacher" (docs/architecture/02, section 2): hard filters in SQL
 * (subject and its level and category, age, gender, language, taking new
 * students or trials), for a student under 18 only teachers cleared for
 * minors (section 15), then free time (daily caps included) and matching
 * per teacher, then a weighted score. exact() and check() look at starts
 * typed at any minute instead of the proposed 15-minute grid.
 *
 * Score (of the weights in the docs, the ones we can measure today):
 * availability 35, load balance 15, soft preferences 10, and for a trial
 * how often the teacher's trials became subscriptions 10 (from the CRM;
 * a teacher with no trials yet counts at the 40% target), scaled to 100.
 * Teacher tier and continuity join when Evaluation lands.
 */
final class FindTeachersQuery
{
    /** A teacher with no trials yet counts at the target rate (docs/architecture/02, KPIs). */
    public const TRIAL_TARGET = 0.4;

    public function __construct(
        private readonly AvailabilityQuery $availability,
        private readonly Matcher $matcher,
        private readonly TrialConversionRates $conversion,
        private readonly MinorsGuard $minors,
    ) {}

    /**
     * @return list<array{teacher: object, score: int, conversion: ?float, utilisation: float, candidates: list<Candidate>,
     *     chips: array<int, list<Candidate>>, pattern: ?list<Candidate>, availability: TeacherAvailability}>
     */
    public function run(TeacherSearch $search): array
    {
        $pref = $search->preference;
        [$teachers, $free] = $this->eligible($search);

        $results = [];
        foreach ($free as $teacherId => $availability) {
            $candidates = $this->matcher->candidates($availability, $pref);
            if ($candidates !== []) {
                $results[] = $this->result($search, $teachers[$teacherId], $availability, $candidates, $this->matcher->suggest($candidates, $pref->perWeek));
            }
        }

        return $this->ranked($search, $results);
    }

    /**
     * "Search at an exact time": the same teachers, but only at the starts
     * typed (student weekday and HH:MM, any minute), each checked by
     * Matcher::exact with the same rules. A teacher free at one of them at
     * least is a result (with those times as chips; all of them make the
     * pattern when they are as many as the lessons a week); the others are
     * "refused", with each time's reasons.
     *
     * @param  list<array{weekday: int, time: string}>  $times
     * @return array{results: list<array<string, mixed>>, refused: list<array{teacher: object, checks: list<ExactCheck>}>}
     */
    public function exact(TeacherSearch $search, array $times, ?int $notBefore = null): array
    {
        $pref = $search->preference;
        [$teachers, $free] = $this->eligible($search);

        $results = [];
        $refused = [];
        foreach ($teachers as $teacherId => $teacher) {
            $availability = $free[$teacherId] ?? null;
            $checks = array_map(fn (array $t) => $availability === null
                ? new ExactCheck($t['weekday'], $t['time'], null, $pref->weeks, $pref->minCoverage, [ExactCheck::SHIFTS => []])
                : $this->matcher->exact($availability, $pref, $t['weekday'], $t['time'], $notBefore), $times);
            $fitting = array_values(array_map(fn (ExactCheck $c) => $c->candidate, array_filter($checks, fn (ExactCheck $c) => $c->fits())));
            if ($fitting === []) {
                $refused[] = ['teacher' => $teacher, 'checks' => $checks];

                continue;
            }
            $pattern = count($fitting) === $pref->perWeek && count(array_unique(array_map(fn (Candidate $c) => $c->studentWeekday, $fitting))) === count($fitting) ? $fitting : null;
            $results[] = $this->result($search, $teacher, $availability, $fitting, $pattern) + ['missed' => array_values(array_filter($checks, fn (ExactCheck $c) => ! $c->fits()))];
        }

        usort($refused, fn (array $a, array $b) => $a['teacher']->name <=> $b['teacher']->name);
        $results = $this->ranked($search, $results);
        // Free at every time typed first.
        usort($results, fn (array $a, array $b) => [count($a['missed']), -$a['score']] <=> [count($b['missed']), -$b['score']]);

        return ['results' => $results, 'refused' => $refused];
    }

    /**
     * One exact start checked for one of the search's teachers (null for a
     * teacher the search would no longer find).
     */
    public function check(TeacherSearch $search, string $teacherId, int $weekday, string $time, ?int $notBefore = null): ?ExactCheck
    {
        [$teachers, $free] = $this->eligible($search, [$teacherId]);
        if (! isset($teachers[$teacherId])) {
            return null;
        }
        $pref = $search->preference;

        return isset($free[$teacherId])
            ? $this->matcher->exact($free[$teacherId], $pref, $weekday, $time, $notBefore)
            : new ExactCheck($weekday, $time, null, $pref->weeks, $pref->minCoverage, [ExactCheck::SHIFTS => []]);
    }

    /**
     * The teachers the hard filters leave, and their free time over the
     * search's weeks.
     *
     * @param  list<string>|null  $only
     * @return array{0: Collection<string, object>, 1: array<string, TeacherAvailability>}
     */
    private function eligible(TeacherSearch $search, ?array $only = null): array
    {
        $pref = $search->preference;

        $teachers = DB::table('teacher_profiles as tp')
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'tp.academy_id')->on('u.id', '=', 'tp.user_id'))
            ->join('teacher_subjects as ts', fn ($j) => $j->on('ts.academy_id', '=', 'tp.academy_id')->on('ts.teacher_id', '=', 'tp.user_id'))
            ->where('ts.subject_id', $search->subjectId)
            ->where('tp.status', 'active')
            ->where('u.status', 'active')
            ->when($only !== null, fn ($q) => $q->whereIn('u.id', $only))
            ->where($search->forTrial ? 'tp.accepts_trials' : 'tp.accepts_new', true)
            ->when($search->gender === 'female_only', fn ($q) => $q->where('tp.gender', 'female'))
            ->when($search->gender === 'male_only', fn ($q) => $q->where('tp.gender', 'male'))
            ->when($search->language, fn ($q, $lang) => $q->whereRaw('tp.languages @> ?::jsonb', [json_encode([$lang])]))
            // The age they teach (none given: any age).
            ->when($search->ageGroup, fn ($q, $group) => $q->whereRaw("(tp.age_groups = '[]'::jsonb or tp.age_groups @> ?::jsonb)", [json_encode([$group])]))
            // The level within their range of the subject's levels (an open end: from the first, or to the last).
            ->when($search->levelId, function ($q, $levelId) {
                $ordinal = (int) DB::table('subject_levels')->where('id', $levelId)->value('ordinal');
                $q->leftJoin('subject_levels as lf', fn ($j) => $j->on('lf.academy_id', '=', 'ts.academy_id')->on('lf.id', '=', 'ts.level_from_id'))
                    ->leftJoin('subject_levels as lt', fn ($j) => $j->on('lt.academy_id', '=', 'ts.academy_id')->on('lt.id', '=', 'ts.level_to_id'))
                    ->where(fn ($w) => $w->whereNull('lf.ordinal')->orWhere('lf.ordinal', '<=', $ordinal))
                    ->where(fn ($w) => $w->whereNull('lt.ordinal')->orWhere('lt.ordinal', '>=', $ordinal));
            })
            // The category among the ones they teach the subject in (none chosen: all of them).
            ->when($search->categoryId, fn ($q, $categoryId) => $q->where(fn ($w) => $w
                ->whereNotExists(fn ($c) => $c->from('teacher_subject_categories as c')->whereColumn('c.teacher_id', 'ts.teacher_id')->whereColumn('c.subject_id', 'ts.subject_id'))
                ->orWhereExists(fn ($c) => $c->from('teacher_subject_categories as c')->whereColumn('c.teacher_id', 'ts.teacher_id')->whereColumn('c.subject_id', 'ts.subject_id')->where('c.category_id', $categoryId))))
            ->select('u.id', 'u.name', 'u.timezone', 'tp.gender', 'tp.languages', 'tp.dialect')
            ->get()
            ->keyBy('id');
        // A student under 18: only teachers cleared for minors (not paused; a valid check where the academy asks for one).
        if ($search->forMinor && $teachers->isNotEmpty()) {
            $teachers = $teachers->only($this->minors->cleared($teachers->keys()->all()));
        }
        foreach ($teachers as $teacher) {
            $teacher->languages = is_string($teacher->languages) ? (json_decode($teacher->languages, true) ?: []) : $teacher->languages;
        }

        if ($teachers->isEmpty()) {
            return [$teachers, []];
        }

        $from = CarbonImmutable::parse($pref->startDate->toDateString(), $pref->timezone)->utc();

        return [$teachers, $this->availability->forTeachers($teachers->keys()->all(), $from, $from->addWeeks($pref->weeks), $search->searcherId)];
    }

    /**
     * @param  list<Candidate>  $candidates
     * @param  ?list<Candidate>  $pattern
     */
    private function result(TeacherSearch $search, object $teacher, TeacherAvailability $availability, array $candidates, ?array $pattern): array
    {
        $pref = $search->preference;
        $coverage = $pattern
            ? array_sum(array_map(fn (Candidate $c) => $c->coverage, $pattern)) / (count($pattern) * $pref->weeks)
            : max(array_map(fn (Candidate $c) => $c->coverage, $candidates)) / $pref->weeks / 2;
        $soft = match ($search->gender) {
            'prefer_female' => $teacher->gender === 'female' ? 1.0 : 0.0,
            'prefer_male' => $teacher->gender === 'male' ? 1.0 : 0.0,
            default => 1.0,
        };

        return [
            'teacher' => $teacher,
            'weighted' => 0.35 * $coverage + 0.15 * (1 - $availability->utilisation()) + 0.10 * $soft,
            'utilisation' => $availability->utilisation(),
            'candidates' => $candidates,
            'chips' => $this->matcher->chips($candidates),
            'pattern' => $pattern,
            'availability' => $availability,
        ];
    }

    /** Scored (with the trial conversion for a trial) and ranked best first. */
    private function ranked(TeacherSearch $search, array $results): array
    {
        $rates = $search->forTrial && $results !== [] ? $this->conversion->forTeachers(array_map(fn (array $r) => $r['teacher']->id, $results)) : [];
        foreach ($results as &$result) {
            $id = $result['teacher']->id;
            $score = $search->forTrial
                ? ($result['weighted'] + 0.10 * ($rates[$id] ?? self::TRIAL_TARGET)) / 0.70
                : $result['weighted'] / 0.60;
            $result['score'] = (int) round(100 * $score);
            $result['conversion'] = $search->forTrial ? ($rates[$id] ?? null) : null;
            unset($result['weighted']);
        }
        unset($result);

        usort($results, fn (array $a, array $b) => [$b['score'], $a['teacher']->name] <=> [$a['score'], $b['teacher']->name]);

        return $results;
    }
}
