<?php

namespace Modules\Learning\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Models\ChallengeClaim;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;

/**
 * A student's journey through their courses, for their own screens: the
 * roadmap and where they are on it, plus what keeps them going, all from
 * what really happened (reports and homework), nothing made up: stars
 * (lessons mastered), points and a rank, the lesson streak, badges, and the
 * pace and the date they should finish at that pace. Points also count the
 * challenge rewards the student collected (ChallengesQuery).
 */
final class JourneyQuery
{
    /** Points: a lesson mastered, a lesson attended, homework handed in on time (plus collected challenge rewards). */
    public const POINTS = ['mastered' => 100, 'attended' => 20, 'on_time' => 30];

    /** Ranks by points. */
    public const RANKS = [0 => 'beginner', 300 => 'explorer', 800 => 'star', 1500 => 'hero', 3000 => 'legend'];

    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly RoadmapQuery $roadmap,
    ) {}

    /**
     * @return array{courses: list<array<string, mixed>>, stars: int, attended: int, on_time: int, streak: int, points: int, challenge_points: int,
     *     rank: array{key: string, points: int, next: ?int, next_key: ?string, progress: int}, badges: list<array{key: string, earned: bool}>}
     */
    public function for(string $studentId, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $enrollments = array_values(array_filter($this->enrollments->forStudent($studentId), fn (EnrollmentData $e) => $e->status->value !== 'ended'));
        $subjects = DB::table('subjects')->pluck(app()->getLocale() === 'ar' ? 'name_ar' : 'name_en', 'id');
        $teachers = DB::table('users')->whereIn('id', array_map(fn (EnrollmentData $e) => $e->teacherId, $enrollments))->pluck('name', 'id');
        $courses = array_map(fn (EnrollmentData $e) => $this->course($e, $studentId, $now, $subjects[$e->subjectId] ?? null, $teachers[$e->teacherId] ?? null), $enrollments);

        $sum = fn (string $key) => (int) array_sum(array_column($courses, $key));
        $streak = $courses === [] ? 0 : max(array_column($courses, 'streak'));
        $bonus = (int) ChallengeClaim::query()->where('student_id', $studentId)->sum('points');
        $points = $sum('points') + $bonus;

        return [
            'courses' => $courses,
            'stars' => $sum('stars'),
            'attended' => $sum('attended'),
            'on_time' => $sum('on_time'),
            'streak' => $streak,
            'points' => $points,
            'challenge_points' => $bonus,
            'rank' => self::rank($points),
            'badges' => array_map(fn (array $b) => ['key' => $b[0], 'earned' => $b[1]], [
                ['first_star', $sum('stars') >= 1],
                ['five_stars', $sum('stars') >= 5],
                ['streak_3', $streak >= 3],
                ['homework_3', $sum('on_time') >= 3],
                ['halfway', collect($courses)->contains(fn (array $c) => ($c['map']['percent'] ?? 0) >= 50)],
                ['level_done', collect($courses)->contains(fn (array $c) => collect($c['worlds'])->contains('state', 'done'))],
            ]),
        ];
    }

    /** @return array<string, mixed> */
    private function course(EnrollmentData $e, string $studentId, CarbonImmutable $now, ?string $subject, ?string $teacher): array
    {
        $map = $this->roadmap->for($e->id);

        // Every finished lesson opens a report (an absence too), written or not: latest first,
        // the streak is the lessons attended since the last absence.
        $kinds = SessionReport::query()->where('enrollment_id', $e->id)->where('student_id', $studentId)
            ->orderByDesc('session_starts_at')->pluck('kind')->all();
        $streak = 0;
        while (($kinds[$streak] ?? null) === 'lesson') {
            $streak++;
        }
        $attended = count(array_filter($kinds, fn (string $k) => $k === 'lesson'));
        $onTime = Homework::query()->where('enrollment_id', $e->id)->where('student_id', $studentId)->whereIn('status', ['submitted', 'reviewed'])
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('homework_submissions as s')->whereColumn('s.homework_id', 'homework.id')->whereColumn('s.submitted_at', '<=', 'homework.due_at'))
            ->count();

        $mastered = $map['lessons']['mastered'] ?? 0;
        $worlds = $map ? $this->worlds($map) : [];

        return [
            'id' => $e->id,
            'subject' => $subject,
            'teacher' => $teacher,
            'map' => $map,
            'stars' => $mastered,
            'attended' => $attended,
            'on_time' => $onTime,
            'streak' => $streak,
            'points' => $mastered * self::POINTS['mastered'] + $attended * self::POINTS['attended'] + $onTime * self::POINTS['on_time'],
            'worlds' => $worlds,
            'level' => $worlds === [] ? null : $this->currentLevel($map, $worlds),
            'next' => $map ? $this->upNext($map) : [],
            'reviews' => $map ? array_values(array_filter($this->lessons($map), fn (array $l) => $l['state'] === 'review')) : [],
            'finish_on' => $map && $map['eta_weeks'] !== null && ! $map['finished'] ? $now->addDays((int) ceil($map['eta_weeks'] * 7)) : null,
        ];
    }

    /** @return array{key: string, points: int, next: ?int, next_key: ?string, progress: int} */
    public static function rank(int $points): array
    {
        [$key, $from, $next, $nextKey] = ['beginner', 0, null, null];
        foreach (self::RANKS as $threshold => $name) {
            if ($points >= $threshold) {
                [$key, $from] = [$name, $threshold];
            } elseif ($next === null) {
                [$next, $nextKey] = [$threshold, $name];
            }
        }

        return ['key' => $key, 'points' => $points, 'next' => $next, 'next_key' => $nextKey,
            'progress' => $next === null ? 100 : (int) floor(($points - $from) * 100 / ($next - $from))];
    }

    /**
     * Each level, as a milestone: done, the one they are in, or still ahead.
     *
     * @return list<array{index: int, title: string, percent: int, state: string, lessons: list<array<string, mixed>>, done: int}>
     */
    private function worlds(array $map): array
    {
        $here = collect($map['levels'])->search(fn (array $l) => $l['has_current']);
        if ($here === false) {
            $here = collect($map['levels'])->search(fn (array $l) => $l['percent'] < 100);
        }
        $here = $here === false ? count($map['levels']) : $here;

        return array_map(function (array $level, int $i) use ($here) {
            $lessons = [...$level['lessons'], ...collect($level['units'])->flatMap(fn (array $u) => array_map(fn (array $l) => $l + ['unit' => $u['title']], $u['lessons']))->all()];

            return [
                'index' => $i + 1,
                'title' => $level['title'],
                'percent' => $level['percent'],
                'state' => match (true) {
                    $i === $here => 'here',
                    $i < $here => 'done',
                    default => 'ahead',
                },
                'lessons' => $lessons,
                'done' => count(array_filter($lessons, fn (array $l) => in_array($l['state'], ['done', 'review'], true))),   // mastered, even if due for review
            ];
        }, $map['levels'], array_keys($map['levels']));
    }

    /**
     * The level the student is in (the last one once they have finished),
     * and how many weeks it should take at their pace.
     *
     * @return array<string, mixed>
     */
    private function currentLevel(array $map, array $worlds): array
    {
        $level = collect($worlds)->firstWhere('state', 'here') ?? $worlds[array_key_last($worlds)];
        $left = count($level['lessons']) - $level['done'];
        $perWeek = (float) ($map['lessons_per_week'] ?? 0);

        return $level + [
            'count' => count($worlds),
            'weeks_left' => $perWeek > 0 && $left > 0 ? (int) ceil($left / $perWeek) : null,
        ];
    }

    /** @return list<array<string, mixed>> the lesson they are on, then the next two still to do */
    private function upNext(array $map): array
    {
        $all = collect($this->lessons($map));
        $from = $all->search(fn (array $l) => $l['state'] === 'here');

        return $from === false ? [] : $all->slice($from)->filter(fn (array $l) => $l['state'] !== 'done')->take(3)->values()->all();
    }

    /** @return list<array<string, mixed>> every lesson, in teaching order */
    private function lessons(array $map): array
    {
        return collect($map['levels'])->flatMap(fn (array $l) => [...$l['lessons'], ...collect($l['units'])->flatMap(fn (array $u) => $u['lessons'])->all()])->values()->all();
    }
}
