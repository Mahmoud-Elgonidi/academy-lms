<?php

namespace Modules\Learning\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Domain\Challenges\ChallengeCatalog;
use Modules\Learning\Models\ChallengeClaim;
use Modules\Learning\Models\Homework;
use Modules\Learning\Models\SessionReport;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\DTOs\LearnerLesson;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\Timezones;

/**
 * A student's challenges for their stage (ChallengeCatalog), worked out each
 * time from what really happened this week (and month), on the student's
 * own clock: each with its target, how far along, and its state (active,
 * missed, done and waiting to be collected, collected). Rewards finished
 * last week or last month can still be collected; levels finished stay
 * collectable. Kids also get their sticker album.
 */
final class ChallengesQuery
{
    public function __construct(
        private readonly AttendanceApi $attendance,
        private readonly EnrollmentApi $enrollments,
        private readonly JourneyQuery $journey,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @return array{stage: string, zone: string, week: array{from: CarbonImmutable, to: CarbonImmutable}, journey: array<string, mixed>,
     *     challenges: list<array<string, mixed>>, stickers: ?array{earned: int, items: list<array{key: string, earned: bool}>}}
     */
    public function for(string $studentId, string $stage, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $stage = array_key_exists($stage, ChallengeCatalog::STAGES) ? $stage : 'classic';
        $zone = $this->zone($studentId);
        $week = $now->setTimezone($zone)->startOfWeek(ChallengeCatalog::WEEK_STARTS)->startOfDay();
        $month = $now->setTimezone($zone)->startOfMonth();
        $journey = $this->journey->for($studentId, $now);
        $claims = ChallengeClaim::query()->where('student_id', $studentId)->get();
        $claimed = $claims->keyBy(fn (ChallengeClaim $c) => $c->challenge.'|'.$c->period);
        $enrollments = array_map(fn (EnrollmentData $e) => $e->id, $this->enrollments->forStudent($studentId));
        $periods = array_unique(array_column(ChallengeCatalog::for($stage), 1));

        $current = [];
        $waiting = [];
        if (in_array('week', $periods, true)) {
            $current = [...$current, ...$this->week($studentId, $stage, $week, $now, $enrollments, $claimed, $claims, 'week')];
            $waiting = [...$waiting, ...$this->week($studentId, $stage, $week->subWeek(), $now, $enrollments, $claimed, $claims, 'last_week')];
        }
        if (in_array('month', $periods, true)) {
            $current = [...$current, ...$this->month($studentId, $stage, $month, $now, $claimed, 'month')];
            $waiting = [...$waiting, ...$this->month($studentId, $stage, $month->subMonth(), $now, $claimed, 'last_month')];
        }
        if (in_array('world', $periods, true)) {
            foreach ($this->bosses($stage, $journey['courses'], $claimed) as $boss) {
                $boss['state'] === 'done' ? $waiting[] = $boss : $current[] = $boss;
            }
        }

        $chests = $claims->where('challenge', 'chest')->count();

        return [
            'stage' => $stage,
            'zone' => $zone,
            'week' => ['from' => $week, 'to' => $week->addWeek()],
            'journey' => $journey,
            // Rewards waiting from earlier first, then this period's.
            'challenges' => [...array_values(array_filter($waiting, fn (array $c) => $c['state'] === 'done')), ...$current],
            'stickers' => $stage !== 'kids' ? null : [
                'earned' => $chests,
                'items' => array_map(fn (string $key, int $i) => ['key' => $key, 'earned' => $i < $chests], ChallengeCatalog::STICKERS, array_keys(ChallengeCatalog::STICKERS)),
            ],
        ];
    }

    /**
     * @param  list<string>  $enrollments
     * @param  Collection<string, ChallengeClaim>  $claimed
     * @param  Collection<int, ChallengeClaim>  $claims
     * @return list<array<string, mixed>>
     */
    private function week(string $studentId, string $stage, CarbonImmutable $from, CarbonImmutable $now, array $enrollments, Collection $claimed, Collection $claims, string $group): array
    {
        $to = $from->addWeek();
        $period = $from->toDateString();
        $lessons = array_values(array_filter($this->attendance->lessonsOfStudent($studentId, $from->utc(), $to->utc()), fn (LearnerLesson $l) => $l->counts()));
        $count = count($lessons);
        $attended = count(array_filter($lessons, fn (LearnerLesson $l) => $l->attended()));
        $missedOne = collect($lessons)->contains(fn (LearnerLesson $l) => $l->missed());
        $open = count(array_filter($lessons, fn (LearnerLesson $l) => ! $l->attended() && ! $l->missed()));

        $row = fn (string $key, int $target, int $progress, bool $missed = false, array $extra = []) => $this->row(
            $stage, $key, $period, $group, $target, $progress, $missed, $to, $claimed, $extra,
        );

        $rows = [];
        foreach (ChallengeCatalog::for($stage) as [$key, $kind]) {
            if ($kind !== 'week' || $key === 'chest') {
                continue;
            }
            $rows[] = match ($key) {
                'attend' => $count === 0 ? null : $row('attend', $count, $attended, $missedOne),
                'homework' => $this->homework($studentId, $stage, $from, $to, $now, $row),
                'practice' => $this->practice($studentId, $from, $to, $now, $row),
                'on_time' => $count === 0 ? null : (function () use ($lessons, $count, $open, $row) {
                    $target = min(2, $count);
                    $onTime = count(array_filter($lessons, fn (LearnerLesson $l) => $l->onTime(ChallengeCatalog::ON_TIME_GRACE_MIN)));

                    return $row('on_time', $target, $onTime, $onTime + $open < $target);
                })(),
                'master' => $count === 0 ? null : $row('master', $stage === 'middle' ? 1 : min(2, $count), $enrollments === [] ? 0 : DB::table('enrollment_progress')
                    ->whereIn('enrollment_id', $enrollments)->where('mastered_at', '>=', $from->utc())->where('mastered_at', '<', $to->utc())->count()),
                'focus' => $count === 0 ? null : $row('focus', min(2, $count), SessionReport::query()->where('student_id', $studentId)->where('kind', 'lesson')
                    ->where('session_starts_at', '>=', $from->utc())->where('session_starts_at', '<', $to->utc())->where('engagement', '>=', 4)->count()),
                default => null,
            };
        }
        $rows = array_values(array_filter($rows));

        // The kids' treasure chest opens once every mission of the week is done: a sticker for the album.
        if (ChallengeCatalog::reward($stage, 'chest') !== null && $rows !== []) {
            $done = count(array_filter($rows, fn (array $r) => in_array($r['state'], ['done', 'claimed'], true)));
            $chest = $claimed->get('chest|'.$period);
            $rows[] = $row('chest', count($rows), $done, false, [
                'sticker' => $chest?->sticker ?? ChallengeCatalog::sticker($claims->where('challenge', 'chest')->count()),
            ]);
        }

        return $rows;
    }

    /**
     * Homework due this week: kids hand it in at all; older students by its due time.
     *
     * @return ?array<string, mixed>
     */
    private function homework(string $studentId, string $stage, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $now, callable $row): ?array
    {
        $due = Homework::query()->with('submissions')->where('student_id', $studentId)
            ->where('due_at', '>=', $from->utc())->where('due_at', '<', $to->utc())->get();
        if ($due->isEmpty()) {
            return null;
        }
        $inTime = fn (Homework $h) => $h->submissions->contains(fn ($s) => $stage === 'kids' || $s->submitted_at->lessThanOrEqualTo($h->due_at));
        $handedIn = $due->filter($inTime)->count();
        $missed = $due->contains(fn (Homework $h) => ! $inTime($h) && $h->due_at->lessThan($now));

        return $row('homework', $due->count(), $handedIn, $missed);
    }

    /**
     * Practice in the games: days of the week with a finished round. Shown
     * only when there is practice to do (a round in the last month, or
     * homework asking for practice), so a learner without any is not held back.
     */
    private function practice(string $studentId, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $now, callable $row): ?array
    {
        $days = DB::table('practice_rounds')->where('student_id', $studentId)
            ->where('played_at', '>=', $from->utc())->where('played_at', '<', $to->utc())->pluck('played_at')
            ->map(fn ($at) => CarbonImmutable::parse($at)->setTimezone($from->getTimezone())->toDateString())->unique()->count();
        $any = $days > 0
            || DB::table('practice_rounds')->where('student_id', $studentId)->where('played_at', '>=', $now->subDays(30)->utc())->exists()
            || Homework::query()->where('student_id', $studentId)->whereNotNull('practice_nodes')->where('status', 'assigned')->exists();

        return $any ? $row('practice', ChallengeCatalog::PRACTICE_DAYS, $days) : null;
    }

    /**
     * A month without missing a lesson.
     *
     * @param  Collection<string, ChallengeClaim>  $claimed
     * @return list<array<string, mixed>>
     */
    private function month(string $studentId, string $stage, CarbonImmutable $from, CarbonImmutable $now, Collection $claimed, string $group): array
    {
        $to = $from->addMonth();
        $lessons = array_values(array_filter($this->attendance->lessonsOfStudent($studentId, $from->utc(), $to->utc()), fn (LearnerLesson $l) => $l->counts()));
        if ($lessons === []) {
            return [];
        }
        $attended = count(array_filter($lessons, fn (LearnerLesson $l) => $l->attended()));
        $missed = collect($lessons)->contains(fn (LearnerLesson $l) => $l->missed());

        return [$this->row($stage, 'month', $from->format('Y-m'), $group, count($lessons), $attended, $missed, $to, $claimed)];
    }

    /**
     * The middle school's "boss": finishing a level. Finished levels stay collectable.
     *
     * @param  list<array<string, mixed>>  $courses
     * @param  Collection<string, ChallengeClaim>  $claimed
     * @return list<array<string, mixed>>
     */
    private function bosses(string $stage, array $courses, Collection $claimed): array
    {
        $rows = [];
        foreach ($courses as $course) {
            foreach ($course['worlds'] as $world) {
                if ($world['state'] === 'ahead' || count($world['lessons']) === 0) {
                    continue;
                }
                $period = 'world:'.$course['id'].':'.$world['index'];
                $done = $world['state'] === 'done';
                $row = $this->row($stage, 'boss', $period, 'world', count($world['lessons']), $done ? count($world['lessons']) : $world['done'], false, null, $claimed, [
                    'world' => ['index' => $world['index'], 'title' => $world['title'], 'subject' => $course['subject']],
                ]);
                if ($row['state'] !== 'claimed') {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    /**
     * @param  Collection<string, ChallengeClaim>  $claimed
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function row(string $stage, string $key, string $period, string $group, int $target, int $progress, bool $missed, ?CarbonImmutable $endsAt, Collection $claimed, array $extra = []): array
    {
        $claim = $claimed->get($key.'|'.$period);

        return [
            'key' => $key,
            'period' => $period,
            'group' => $group,
            'target' => $target,
            'progress' => min($progress, $target),
            'state' => match (true) {
                $claim !== null => 'claimed',
                $progress >= $target => 'done',
                $missed => 'missed',
                default => 'active',
            },
            'reward' => $claim?->points ?? (int) ChallengeCatalog::reward($stage, $key),
            'ends_at' => $endsAt,
        ] + $extra + ['sticker' => null, 'world' => null];
    }

    /** The student's own clock: their profile's zone, else the academy's. */
    private function zone(string $studentId): string
    {
        $zone = DB::table('users')->where('id', $studentId)->value('timezone');

        return Timezones::isValid($zone) ? $zone : ($this->tenants->current()?->defaultTimezone ?? 'UTC');
    }
}
