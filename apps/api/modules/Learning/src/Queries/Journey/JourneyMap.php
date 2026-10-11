<?php

namespace Modules\Learning\Queries\Journey;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Contracts\DTOs\JourneyStation;
use Modules\Learning\Contracts\JourneyGames;
use Modules\Learning\Domain\Challenges\ChallengeCatalog;
use Modules\Learning\Domain\Journey\Stars;
use Modules\Learning\Models\ChallengeClaim;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\LearningSettings;
use Modules\Learning\Models\ReportItem;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\RoadmapQuery;
use Modules\Scheduling\Contracts\DTOs\LearnerLesson;
use Modules\SharedKernel\Games\CatalogIsland;
use Modules\SharedKernel\Games\GameCatalog;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\Timezones;

/**
 * A student's journey (docs/architecture/02, section 18), built from their
 * academy's own curriculum and nothing else: for each course, its levels as
 * islands (drawn as the world's islands in turn) and its lessons as stations
 * on them, where the student stands (the teacher's reports), the missions to
 * do now (the next lesson, the teacher's homework, reviews due), the stars
 * (StarsQuery), the week's chest and, when the academy has games, the games
 * on each lesson the student may practise (JourneyGames). Nothing here knows
 * a subject: an academy teaching the Quran and one teaching English get the
 * same journey over their own programs.
 */
final class JourneyMap
{
    /** Lessons ahead shown as missions. */
    public const LESSONS_AHEAD_DAYS = 14;

    /** Homework past its due date still shown (to hand in late). */
    public const LATE_HOMEWORK_DAYS = 7;

    /** Homework handed in stays on the board this long, done. */
    public const DONE_HOMEWORK_DAYS = 2;

    public const REVIEWS = 3;

    /** The last lesson counts for practice this long (as PracticeFocusQuery). */
    public const LAST_LESSON_DAYS = 14;

    public const STYLES = ['playful', 'calm', 'plain'];

    /** @var ?list<CatalogIsland> */
    private ?array $islands = null;

    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly RoadmapQuery $roadmap,
        private readonly StarsQuery $stars,
        private readonly GameCatalog $catalog,
        private readonly JourneyGames $games,
        private readonly TenantContext $tenants,
    ) {}

    /** @return array<string, mixed> */
    public function for(string $studentId, string $stage, bool $guardian = false, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $style = LearningSettings::current()->journey_style;
        $stars = $this->stars->for($studentId, $now);
        $courses = $this->courses($studentId, $now);
        $missions = $this->missions($studentId, $courses, $stars['lessons_list'], $now);

        // Which stations carry which missions (a flag on the map).
        foreach ($courses as &$course) {
            foreach ($course['islands'] as &$island) {
                foreach ($island['stations'] as &$station) {
                    $station['missions'] = array_values(array_map(fn (array $m) => $m['key'],
                        array_filter($missions, fn (array $m) => $m['station_id'] === $station['id'] && $m['state'] !== 'done')));
                }
            }
        }
        unset($course, $island, $station);

        $layer = $style === 'plain' ? null : $this->games->layer($studentId, $stage, $this->stationsOf($courses, $missions), $stars['total'], $guardian);
        if ($layer !== null) {
            foreach ($courses as &$course) {
                foreach ($course['islands'] as &$island) {
                    $island['creatures'] = $island['art'] === null ? [] : ($layer->islands[$island['art']['key']]['creatures'] ?? []);
                    $island['next_creature'] = $island['art'] === null ? null : ($layer->islands[$island['art']['key']]['next'] ?? null);
                    foreach ($island['stations'] as &$station) {
                        $station['games'] = $station['playable'] ? ($layer->games[$station['id']] ?? []) : [];
                        $station['race'] = $layer->stats[$station['id']] ?? null;
                    }
                }
            }
            unset($course, $island, $station);
        }

        $last = collect($stars['by_lesson'])->filter(fn (array $l) => $l['starts_at']->greaterThan($now->subDays(7)))->sortByDesc(fn (array $l) => $l['starts_at']->getTimestamp());
        $total = $style === 'plain' ? 0 : $stars['total'];

        return [
            'stage' => $stage,
            'style' => $style,
            'stars' => [
                'total' => $total,
                'rank' => Stars::rank($total),
                'from' => array_intersect_key($stars, array_flip(['lessons', 'practice', 'homework', 'mastered', 'chest'])),
            ],
            'streak' => $this->streak($stars['lessons_list']),
            'last_lesson' => $last->isEmpty() ? null : ['id' => $last->keys()->first()] + $last->first(),
            'courses' => $courses,
            'missions' => $missions,
            'chest' => $style === 'plain' ? null : $this->chest($studentId, $stage, $stars['lessons_list'], $now),
            'games' => $layer,
        ];
    }

    /**
     * The lessons the student may practise now, for the games: what they have learnt (mastered, under review),
     * where they are, and the lessons of their last report and of homework asking for practice.
     *
     * @return list<JourneyStation>
     */
    public function stations(string $studentId, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $courses = $this->courses($studentId, $now);

        return $this->stationsOf($courses, $this->homeworkMissions($studentId, $courses, $now));
    }

    /**
     * @param  list<array<string, mixed>>  $courses
     * @param  list<array<string, mixed>>  $missions
     * @return list<JourneyStation>
     */
    private function stationsOf(array $courses, array $missions): array
    {
        $games = [];
        foreach ($missions as $m) {
            if ($m['kind'] === 'homework' && $m['game'] !== null && $m['station_id'] !== null) {
                $games[$m['station_id']] ??= $m['game'];
            }
        }
        $out = [];
        foreach ($courses as $course) {
            foreach ($course['islands'] as $island) {
                foreach ($island['stations'] as $s) {
                    if ($s['playable']) {
                        $out[] = new JourneyStation($s['id'], $course['enrollment_id'], $island['art']['key'] ?? null, $s['state'], $games[$s['id']] ?? null);
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Each course not ended: its islands (levels) and stations (lessons).
     *
     * @return list<array<string, mixed>>
     */
    private function courses(string $studentId, CarbonImmutable $now): array
    {
        $enrollments = array_values(array_filter($this->enrollments->forStudent($studentId), fn (EnrollmentData $e) => $e->status->value !== 'ended'));
        $ar = app()->getLocale() === 'ar';
        $subjects = DB::table('subjects')->whereIn('id', array_map(fn (EnrollmentData $e) => $e->subjectId, $enrollments))->pluck($ar ? 'name_ar' : 'name_en', 'id');
        $teachers = DB::table('users')->whereIn('id', array_map(fn (EnrollmentData $e) => $e->teacherId, $enrollments))->pluck('name', 'id');
        $practiceNodes = Homework::query()->where('student_id', $studentId)->whereNotNull('practice_nodes')->where('status', 'assigned')
            ->pluck('practice_nodes')->flatten()->all();

        return array_map(function (EnrollmentData $e) use ($subjects, $teachers, $practiceNodes, $now) {
            $map = $this->roadmap->for($e->id);
            $taught = $this->lastTaught($e->id, $now);
            $due = DB::table('enrollment_progress')->where('enrollment_id', $e->id)->whereNotNull('mastered_at')
                ->where('next_review_at', '<=', $now)->pluck('node_id')->flip()->all();
            $islands = [];
            foreach ($map['levels'] ?? [] as $i => $level) {
                $lessons = [
                    ...array_map(fn (array $l) => $l + ['unit' => null], $level['lessons']),
                    ...collect($level['units'])->flatMap(fn (array $u) => array_map(fn (array $l) => $l + ['unit' => $u['title']], $u['lessons']))->all(),
                ];
                $art = $this->art($i);
                $islands[] = [
                    'id' => $level['id'],
                    'index' => $i + 1,
                    'title' => $level['title'],
                    'percent' => $level['percent'],
                    'state' => null,   // below, once the current island is known
                    'has_current' => $level['has_current'],
                    'art' => $art === null ? null : ['key' => $art->key, 'name' => $art->name(), 'emoji' => $art->emoji, 'colors' => $art->colors, 'music' => $art->music],
                    'creatures' => [],
                    'next_creature' => null,
                    'stations' => array_map(fn (array $l) => [
                        'id' => $l['id'],
                        'title' => $l['title'],
                        'unit' => $l['unit'],
                        'state' => $l['state'],
                        'due_review' => isset($due[$l['id']]),
                        'playable' => in_array($l['state'], ['done', 'review', 'here'], true) || in_array($l['id'], $taught, true) || in_array($l['id'], $practiceNodes, true),
                        'practice' => $l['practice'],
                        'missions' => [],
                        'games' => [],
                        'race' => null,
                    ], $lessons),
                    'lessons' => count($lessons),
                    'done' => count(array_filter($lessons, fn (array $l) => in_array($l['state'], ['done', 'review'], true))),
                ];
            }
            $here = collect($islands)->search(fn (array $is) => $is['has_current']);
            if ($here === false) {
                $here = collect($islands)->search(fn (array $is) => $is['done'] < $is['lessons']);
            }
            $here = $here === false ? count($islands) : $here;
            foreach ($islands as $k => &$island) {
                $island['state'] = match (true) {
                    $k === $here => 'here',
                    $k < $here => 'done',
                    default => 'ahead',
                };
                unset($island['has_current']);
            }
            unset($island);

            return [
                'enrollment_id' => $e->id,
                'subject' => $subjects[$e->subjectId] ?? null,
                'teacher' => $teachers[$e->teacherId] ?? null,
                'program' => $map['program']['name'] ?? null,
                /** false: the course follows no published curriculum (its missions only). */
                'has_curriculum' => $map !== null,
                'percent' => $map['percent'] ?? null,
                'finished' => (bool) ($map['finished'] ?? false),
                'lessons' => $map['lessons'] ?? null,
                'eta_weeks' => $map['eta_weeks'] ?? null,
                'finish_on' => $map && $map['eta_weeks'] !== null && ! $map['finished'] ? $now->addDays((int) ceil($map['eta_weeks'] * 7)) : null,
                'current' => $map['current'] ?? null,
                'islands' => $islands,
            ];
        }, $enrollments);
    }

    /** @return list<string> the lessons of the course's last report (still recent) */
    private function lastTaught(string $enrollmentId, CarbonImmutable $now): array
    {
        $report = SessionReport::query()->where('enrollment_id', $enrollmentId)->where('kind', 'lesson')->whereNotNull('submitted_at')
            ->where('session_starts_at', '>=', $now->subDays(self::LAST_LESSON_DAYS))->latest('session_starts_at')->first();

        return $report === null ? [] : ReportItem::query()->where('report_id', $report->id)->pluck('node_id')->all();
    }

    /** The world island a level is drawn as: the islands in turn. */
    private function art(int $index): ?CatalogIsland
    {
        $this->islands ??= array_values($this->catalog->islands());

        return $this->islands === [] ? null : $this->islands[$index % count($this->islands)];
    }

    /**
     * The missions: the next lesson of each course, the teacher's homework, and lessons due for review.
     *
     * @param  list<array<string, mixed>>  $courses
     * @param  list<LearnerLesson>  $lessons
     * @return list<array<string, mixed>>
     */
    private function missions(string $studentId, array $courses, array $lessons, CarbonImmutable $now): array
    {
        $byCourse = collect($courses)->keyBy('enrollment_id');
        $opensBefore = (int) config('classroom.opens_before_min', 10);
        $closesAfter = (int) config('classroom.closes_after_min', 10);

        $next = [];
        foreach ($lessons as $l) {
            if (! in_array($l->status, ['scheduled', 'in_progress'], true) || ($l->endsAt ?? $l->startsAt)->lessThan($now)
                || $l->startsAt->greaterThan($now->addDays(self::LESSONS_AHEAD_DAYS))) {
                continue;
            }
            $key = $l->enrollmentId ?? 'none';
            if (isset($next[$key])) {
                continue;
            }
            $course = $l->enrollmentId === null ? null : $byCourse->get($l->enrollmentId);
            [$island, $station] = $this->whereNow($course);
            $opensAt = $l->startsAt->subMinutes($opensBefore);
            $next[$key] = [
                'key' => 'lesson:'.$l->id,
                'kind' => 'lesson',
                'state' => 'open',
                'enrollment_id' => $l->enrollmentId,
                'island_id' => $island,
                'station_id' => $station,
                'title' => $course['subject'] ?? null,
                'lesson_id' => $l->id,
                'starts_at' => $l->startsAt,
                'ends_at' => $l->endsAt,
                'join' => ['open' => $now->greaterThanOrEqualTo($opensAt) && $now->lessThanOrEqualTo(($l->endsAt ?? $l->startsAt)->addMinutes($closesAfter)), 'opens_at' => $opensAt],
                /** The stars the lesson can bring: three on time, one more for staying to the end. */
                'stars' => 4,
            ];
        }
        $soon = array_filter($next, fn (array $m) => $m['starts_at']->lessThanOrEqualTo($now->addHours(2)));
        $later = array_filter($next, fn (array $m) => $m['starts_at']->greaterThan($now->addHours(2)));

        $reviews = [];
        foreach ($courses as $course) {
            foreach ($course['islands'] as $island) {
                foreach ($island['stations'] as $s) {
                    if ($s['due_review'] && count($reviews) < self::REVIEWS) {
                        $reviews[] = [
                            'key' => 'review:'.$s['id'],
                            'kind' => 'review',
                            'state' => 'open',
                            'enrollment_id' => $course['enrollment_id'],
                            'island_id' => $island['id'],
                            'station_id' => $s['id'],
                            'title' => $s['title'],
                        ];
                    }
                }
            }
        }

        return [...array_values($soon), ...$this->homeworkMissions($studentId, $courses, $now), ...array_values($later), ...$reviews];
    }

    /**
     * The teacher's homework: open (and late, a while), and handed in lately (done).
     *
     * @param  list<array<string, mixed>>  $courses
     * @return list<array<string, mixed>>
     */
    private function homeworkMissions(string $studentId, array $courses, CarbonImmutable $now): array
    {
        $byCourse = collect($courses)->keyBy('enrollment_id');
        $rows = Homework::query()->with('submissions')->where('student_id', $studentId)
            ->where(fn ($q) => $q->where(fn ($q) => $q->whereIn('status', ['assigned', 'missed'])->where('due_at', '>', $now->subDays(self::LATE_HOMEWORK_DAYS)))
                ->orWhere(fn ($q) => $q->whereIn('status', ['submitted', 'reviewed'])->where('updated_at', '>', $now->subDays(self::DONE_HOMEWORK_DAYS))))
            ->orderBy('due_at')->get();

        return $rows->map(function (Homework $h) use ($byCourse, $now) {
            $course = $h->enrollment_id === null ? null : $byCourse->get($h->enrollment_id);
            // On the furthest of its lessons (the practice it asks for, else its report's lessons): what it was set on.
            $nodes = $h->practice_nodes ?? ($h->report_id === null ? [] : ReportItem::query()->where('report_id', $h->report_id)->pluck('node_id')->all());
            [$island, $station] = [null, null];
            foreach ($course['islands'] ?? [] as $is) {
                foreach ($is['stations'] as $st) {
                    if (in_array($st['id'], $nodes, true)) {
                        [$island, $station] = [$is['id'], $st['id']];
                    }
                }
            }
            $first = $h->submissions->min('submitted_at');
            $done = in_array($h->status, ['submitted', 'reviewed'], true);

            return [
                'key' => 'homework:'.$h->id,
                'kind' => 'homework',
                'state' => $done ? 'done' : ($h->due_at->lessThan($now) ? 'late' : 'open'),
                'enrollment_id' => $h->enrollment_id,
                'island_id' => $island,
                'station_id' => $station,
                'title' => $h->title,
                'homework_id' => $h->id,
                'due_at' => $h->due_at,
                'accepts' => $h->accepts,
                /** It asks for practice in the games (on its lessons), with the game the teacher chose. */
                'practice' => $h->practice_nodes !== null,
                'game' => $h->practice_game,
                'result' => $h->practice_result,
                /** Stars it brought (done), else the ones it can still bring. */
                'stars' => $done ? Stars::homework($h->due_at, $first === null ? null : CarbonImmutable::parse($first))
                    : ($h->due_at->lessThan($now) ? Stars::HOMEWORK_LATE : Stars::HOMEWORK_ON_TIME),
            ];
        })->values()->all();
    }

    /**
     * Where a course stands now: the island and the station of its current lesson.
     *
     * @param  ?array<string, mixed>  $course
     * @return array{0: ?string, 1: ?string}
     */
    private function whereNow(?array $course): array
    {
        foreach ($course['islands'] ?? [] as $island) {
            foreach ($island['stations'] as $s) {
                if ($s['state'] === 'here') {
                    return [$island['id'], $s['id']];
                }
            }
        }

        return [null, null];
    }

    /**
     * The week's chest (Saturday to Friday, on the student's clock): every lesson of the week attended and every
     * homework due in it handed in. Last week's, done and not opened yet, comes first.
     *
     * @param  list<LearnerLesson>  $lessons
     * @return ?array<string, mixed>
     */
    private function chest(string $studentId, string $stage, array $lessons, CarbonImmutable $now): ?array
    {
        $zone = $this->zone($studentId);
        $week = $now->setTimezone($zone)->startOfWeek(ChallengeCatalog::WEEK_STARTS)->startOfDay();
        $opened = ChallengeClaim::query()->where('student_id', $studentId)->where('challenge', 'chest')->get();
        $last = $this->week($studentId, $stage, $week->subWeek(), $lessons, $opened, $now);
        if ($last !== null && $last['state'] === 'done') {
            return $last;
        }

        return $this->week($studentId, $stage, $week, $lessons, $opened, $now);
    }

    /**
     * @param  list<LearnerLesson>  $lessons
     * @param  Collection<int, ChallengeClaim>  $opened
     * @return ?array<string, mixed>
     */
    private function week(string $studentId, string $stage, CarbonImmutable $from, array $lessons, $opened, CarbonImmutable $now): ?array
    {
        $to = $from->addWeek();
        $mine = array_filter($lessons, fn (LearnerLesson $l) => $l->counts() && $l->startsAt->greaterThanOrEqualTo($from) && $l->startsAt->lessThan($to));
        $homework = Homework::query()->with('submissions')->where('student_id', $studentId)
            ->where('due_at', '>=', $from->utc())->where('due_at', '<', $to->utc())->get();
        $target = count($mine) + $homework->count();
        if ($target === 0) {
            return null;
        }
        $handedIn = fn (Homework $h) => $h->submissions->isNotEmpty();
        $progress = count(array_filter($mine, fn (LearnerLesson $l) => $l->attended())) + $homework->filter($handedIn)->count();
        $missed = collect($mine)->contains(fn (LearnerLesson $l) => $l->missed())
            || $homework->contains(fn (Homework $h) => ! $handedIn($h) && $h->due_at->lessThan($now) && $to->lessThanOrEqualTo($now));
        $period = $from->toDateString();
        $claim = $opened->firstWhere('period', $period);

        return [
            'period' => $period,
            'target' => $target,
            'progress' => min($progress, $target),
            'state' => match (true) {
                $claim !== null => 'opened',
                $progress >= $target => 'done',
                $missed => 'missed',
                default => 'active',
            },
            'stars' => Stars::CHEST,
            /** The sticker it brings for the kids' album (the one it brought, once opened). */
            'sticker' => $stage === 'kids' ? ($claim?->sticker ?? ChallengeCatalog::sticker($opened->count())) : null,
            'ends_at' => $to,
        ];
    }

    /** Days start on the student's own clock: their zone, else the academy's. */
    private function zone(string $studentId): string
    {
        $zone = DB::table('users')->where('id', $studentId)->value('timezone');

        return Timezones::isValid($zone) ? $zone : ($this->tenants->current()?->defaultTimezone ?? 'UTC');
    }

    /** Lessons attended in a row, newest first (an absence ends it; cancelled ones and the teacher's absence do not count). */
    private function streak(array $lessons): int
    {
        $n = 0;
        foreach (array_reverse($lessons) as $l) {
            /** @var LearnerLesson $l */
            if ($l->attended()) {
                $n++;
            } elseif ($l->missed()) {
                break;
            }
        }

        return $n;
    }
}
