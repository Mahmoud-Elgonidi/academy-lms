<?php

namespace Modules\Scheduling\Queries;

use Modules\Scheduling\Contracts\DTOs\TeacherSearch;
use Modules\Scheduling\Domain\Availability\Candidate;
use Modules\Scheduling\Domain\Availability\StudentPreference;

/**
 * "Available now" on Find a teacher: where the teachers a search would find
 * are free across the week, before anyone marks the student's hours. It runs
 * FindTeachersQuery with the same filters, length, weeks, start and minimum
 * of weeks, but with every hour from 07:00 to midnight of every day on the
 * student's clock, so every rule of the search (minors, time off, holidays,
 * daily caps, lessons and holds) holds here too; nothing is stored.
 *
 *  - cells[weekday][hour]: the teachers who could take a lesson that starts
 *    and ends inside that hour of the student's clock (what marking that one
 *    hour would find), with the first three names;
 *  - suggestions: up to six sets of `perWeek` weekly times, each one a
 *    teacher's own suggested pattern, ranked by how many teachers are free
 *    at every time of the set, then by the best of them.
 */
final class FreeTimesQuery
{
    public const FIRST_HOUR = 7;

    public const LAST_HOUR = 23;

    public const SUGGESTIONS = 6;

    public const NAMES = 3;

    /** @var array<string, array> answers worked out in this request */
    private array $memo = [];

    public function __construct(private readonly FindTeachersQuery $teachers) {}

    /**
     * @return array{
     *     cells: array<int, array<int, array{count: int, names: list<string>}>>,
     *     suggestions: list<array{times: list<array{weekday: int, time: string}>, count: int, teacher: string, teacher_id: string, score: int}>,
     *     teachers: int,
     * }
     */
    public function run(TeacherSearch $base): array
    {
        return $this->memo[serialize($base)] ??= $this->work($base);
    }

    private function work(TeacherSearch $base): array
    {
        $pref = $base->preference;
        $everyHour = array_fill_keys(range(1, 7), range(self::FIRST_HOUR, self::LAST_HOUR));
        $search = new TeacherSearch(
            subjectId: $base->subjectId,
            preference: new StudentPreference($pref->timezone, StudentPreference::windowsFromHours($everyHour), $pref->perWeek, $pref->durationMin,
                $pref->startDate, $pref->weeks, $pref->minCoverage),
            gender: $base->gender,
            language: $base->language,
            forTrial: $base->forTrial,
            searcherId: $base->searcherId,
            levelId: $base->levelId,
            categoryId: $base->categoryId,
            ageGroup: $base->ageGroup,
            forMinor: $base->forMinor,
        );
        $results = $this->teachers->run($search);

        // Ranked best first, so the first names of a cell are the best fits.
        $cells = [];
        $times = [];
        foreach ($results as $result) {
            $id = $result['teacher']->id;
            foreach ($result['candidates'] as $candidate) {
                $times[$id][self::timeKey($candidate)] = true;
                [$hour, $minute] = array_map('intval', explode(':', $candidate->studentTime));
                if ($hour < self::FIRST_HOUR || $minute + $pref->durationMin > 60) {
                    continue;
                }
                $cell = &$cells[$candidate->studentWeekday][$hour];
                $cell ??= ['count' => 0, 'names' => [], 'ids' => []];
                if (! isset($cell['ids'][$id])) {
                    $cell['ids'][$id] = true;
                    $cell['count']++;
                    if (count($cell['names']) < self::NAMES) {
                        $cell['names'][] = $result['teacher']->name;
                    }
                }
                unset($cell);
            }
        }
        foreach ($cells as $day => $hours) {
            foreach ($hours as $hour => $cell) {
                unset($cells[$day][$hour]['ids']);
            }
        }

        return ['cells' => $cells, 'suggestions' => $this->suggestions($results, $times), 'teachers' => count($results)];
    }

    /**
     * @param  list<array<string, mixed>>  $results  ranked best first
     * @param  array<string, array<string, true>>  $times  teacher => their weekly times on the student's clock
     */
    private function suggestions(array $results, array $times): array
    {
        $sets = [];
        foreach ($results as $result) {
            if (! $result['pattern']) {
                continue;
            }
            $keys = array_map(fn (Candidate $c) => self::timeKey($c), $result['pattern']);
            sort($keys);
            $sets[implode(',', $keys)] ??= $keys;
        }

        $suggestions = [];
        foreach ($sets as $keys) {
            // Everyone free at every time of the set; the first of them (ranked) is the best.
            $free = array_values(array_filter($results, fn (array $r) => array_diff($keys, array_keys($times[$r['teacher']->id] ?? [])) === []));
            $suggestions[] = [
                'times' => array_map(function (string $key) {
                    [$weekday, $time] = explode('|', $key);

                    return ['weekday' => (int) $weekday, 'time' => $time];
                }, $keys),
                'count' => count($free),
                'teacher' => $free[0]['teacher']->name,
                'teacher_id' => $free[0]['teacher']->id,
                'score' => $free[0]['score'],
            ];
        }

        usort($suggestions, fn (array $a, array $b) => [$b['count'], $b['score'], self::firstTime($a)] <=> [$a['count'], $a['score'], self::firstTime($b)]);

        return array_slice($suggestions, 0, self::SUGGESTIONS);
    }

    private static function timeKey(Candidate $c): string
    {
        return $c->studentWeekday.'|'.$c->studentTime;
    }

    private static function firstTime(array $suggestion): string
    {
        return $suggestion['times'][0]['weekday'].'|'.$suggestion['times'][0]['time'];
    }
}
