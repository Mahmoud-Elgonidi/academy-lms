<?php

namespace Modules\Scheduling\Queries\Start;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Scheduling\Domain\Availability\Candidate;
use Modules\Scheduling\Domain\Availability\IntervalSet;
use Modules\Scheduling\Domain\Availability\Matcher;
use Modules\Scheduling\Domain\Availability\StudentPreference;
use Modules\Scheduling\Domain\Availability\TeacherAvailability;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Queries\AvailabilityQuery;
use Modules\Scheduling\Queries\Groups\OpenGroups;
use Modules\Scheduling\Support\MinorsGuard;

/**
 * The weekly times a family may start a course at with one teacher (docs/
 * architecture/02, section 27, J3), on the family's clock: the teacher's free
 * time (AvailabilityQuery: shifts less lessons, the daily cap and others'
 * holds), each weekly time checked in each of the first WEEKS weeks, on whole
 * and half hours of the family's clock, and never when the learner already
 * has a lesson. A week that falls on an academy holiday or on the teacher's
 * approved leave does not take the weekly time away: that week is skipped
 * (no lesson is booked that day, as rule 15 would never hold one) as long as
 * at least MIN_WEEKS of the WEEKS weeks have their lesson; anything else (a
 * lesson, outside the shifts, the daily cap, a hold) still has to be free
 * every week. The first lesson is the first week not skipped, and far enough
 * ahead for the family to pay before an unpaid lesson would be cancelled
 * (rule 16: unpaid_cancel_hours, plus PAY_HOURS to pay in). A group plan's
 * "times" are the open groups of its shape instead (seats free, the
 * learner's age, a teacher cleared for a minor).
 */
final class StartSlots
{
    /** Each weekly time is checked in each of the first this many weeks. */
    public const WEEKS = 4;

    /**
     * At least this many of them have their lesson: one week may be skipped for a holiday or the teacher's leave, so a
     * course always starts with three lessons of its first four weeks at that time and its first lesson in its first two
     * weeks; a second week lost (a long leave, two holidays) means another day or teacher fits better.
     */
    public const MIN_WEEKS = 3;

    /** Why a week is skipped: the academy's holiday (rule 15) or the teacher's approved leave (never its own reason). */
    public const HOLIDAY = 'holiday';

    public const TEACHER_LEAVE = 'teacher_leave';

    /** At least this long to pay between starting and the first lesson's unpaid deadline. */
    public const PAY_HOURS = 12;

    public function __construct(
        private readonly AvailabilityQuery $availability,
        private readonly Matcher $matcher,
        private readonly OpenGroups $groups,
        private readonly MinorsGuard $minors,
    ) {}

    /** The hours from now before the first lesson a family may start with (always time to pay before rule 16 cancels it). */
    public static function noticeHours(): int
    {
        return max(self::PAY_HOURS, self::unpaidHours() + self::PAY_HOURS);
    }

    /** Rule 16: an unpaid lesson is cancelled this many hours before it starts (0: never). */
    public static function unpaidHours(): int
    {
        return (int) PolicyVersion::settingsInForce()->get('unpaid_cancel_hours');
    }

    /** The first day a course may start on, on the family's clock: the day after the notice runs out. */
    public static function firstDay(string $timezone, ?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now())->addHours(self::noticeHours())->setTimezone($timezone)->addDay()->startOfDay();
    }

    /**
     * The teacher's weekly times for a private plan's lessons, on the family's clock, each free in at least MIN_WEEKS of
     * the first WEEKS weeks from the first day and skipped only for a holiday or the teacher's leave in the others, and
     * none when the learner is busy at a lesson of it. Keyed by Candidate::key() (the teacher's clock); each one's
     * occurrences are its lessons (the first is the course's first lesson at that time) and its skipped the weeks left out.
     *
     * @return array<string, Candidate>
     */
    public function private(object $teacher, string $learnerId, PlanOption $plan, string $timezone, ?string $viewerId, ?CarbonImmutable $now = null): array
    {
        $first = self::firstDay($timezone, $now);
        $from = $first->utc();
        $to = $from->addWeeks(self::WEEKS)->addDay();
        $known = $this->availability->forTeachers([(string) $teacher->id], $from, $to, $viewerId)[(string) $teacher->id] ?? null;
        if ($known === null) {
            return [];
        }
        // Holidays and leave set aside: the times free every week but for them (shifts, lessons, holds and the cap still count).
        $taken = new IntervalSet(array_merge($known->booked->intervals, $known->held->intervals, $known->capped->intervals));
        $open = new TeacherAvailability($known->teacherId, $known->timezone, $known->planned, $known->planned->minus($taken));

        $windows = array_map(fn (int $d) => ['weekday' => $d, 'start' => 0, 'end' => 24 * 60 + $plan->sessionMinutes], range(1, 7));
        $pref = new StudentPreference($timezone, $windows, max(1, $plan->sessionsPerWeek), $plan->sessionMinutes, $first, self::WEEKS, self::WEEKS);
        $busy = DB::table('session_participants')->where('user_id', $learnerId)->where('is_active', true)
            ->whereRaw('block_period && tstzrange(?, ?)', [$from, $to])
            ->selectRaw('extract(epoch from lower(block_period))::bigint as s, extract(epoch from upper(block_period))::bigint as e')->get();
        $duration = $plan->sessionMinutes * 60;

        $out = [];
        foreach ($this->matcher->candidates($open, $pref) as $candidate) {
            if (! in_array(substr($candidate->studentTime, 3, 2), ['00', '30'], true) || ! $candidate->full()) {
                continue;
            }
            $lessons = [];
            $skipped = [];
            foreach ($candidate->occurrences as $ts) {
                if (! $known->free->contains($ts, $ts + $duration)) {
                    $reason = self::skipReason($known, $ts, $ts + $duration);
                    if ($reason === null) {
                        continue 2;
                    }
                    $skipped[] = ['at' => $ts, 'date' => CarbonImmutable::createFromTimestamp($ts, $known->timezone)->toDateString(), 'reason' => $reason];

                    continue;
                }
                foreach ($busy as $b) {
                    if ($ts < (int) $b->e && $ts + $duration > (int) $b->s) {
                        continue 3;     // the learner has a lesson then
                    }
                }
                $lessons[] = $ts;
            }
            if (count($lessons) < self::MIN_WEEKS) {
                continue;
            }
            $out[$candidate->key()] = new Candidate($candidate->teacherWeekday, $candidate->teacherTime, $candidate->studentWeekday, $candidate->studentTime,
                count($lessons), self::WEEKS, $lessons, array_column($skipped, 'date'), false, $skipped);
        }

        return $out;
    }

    /** Why a lesson that is not free may be skipped (the academy is closed, the teacher is on leave); null when it may not. */
    private static function skipReason(TeacherAvailability $known, int $start, int $end): ?string
    {
        return match (true) {
            $known->closed->overlaps($start, $end) => self::HOLIDAY,
            $known->off->overlaps($start, $end) => self::TEACHER_LEAVE,
            default => null,
        };
    }

    /**
     * How many days longer the first block's minutes must last for the weeks these weekly times skip: as many as its
     * last lesson moves later for them (rule 15 gives a holiday's days back the same way), and only as far as the plan's
     * validity, counted from now (the earliest it can be paid), would otherwise end before that lesson. 0 when nothing is
     * skipped in the block, or the validity already reaches it.
     *
     * @param  list<Candidate>  $chosen
     */
    public static function extraDays(array $chosen, int $lessonsInBlock, int $validityDays, ?CarbonImmutable $now = null): int
    {
        $skips = [];
        foreach ($chosen as $c) {
            array_push($skips, ...array_column($c->skipped, 'at'));
        }
        if ($lessonsInBlock <= 0 || $skips === []) {
            return 0;
        }
        $all = [];
        foreach ($chosen as $c) {
            $weeks = array_merge($c->occurrences, array_column($c->skipped, 'at'));
            sort($weeks);
            // Past the first weeks the same weekly time goes on (a day's precision is all this needs).
            while (count($weeks) < $lessonsInBlock + count($c->skipped)) {
                $weeks[] = end($weeks) + 7 * 86400;
            }
            array_push($all, ...$weeks);
        }
        sort($all);
        $taught = array_values(array_diff($all, $skips));
        $without = $all[$lessonsInBlock - 1];
        $with = $taught[$lessonsInBlock - 1];
        $expires = ($now ?? CarbonImmutable::now())->addDays($validityDays)->endOfDay()->getTimestamp();
        if ($with <= $without || $with <= $expires) {
            return 0;
        }

        return (int) min(ceil(($with - $without) / 86400), ceil(($with - $expires) / 86400));
    }

    /** The suggested pattern among these times (one a day, most regular), as keys; null without enough days. */
    public function suggest(array $candidates, int $perWeek): ?array
    {
        $pattern = $this->matcher->suggest(array_values($candidates), max(1, $perWeek));

        return $pattern === null ? null : array_map(fn (Candidate $c) => $c->key(), $pattern);
    }

    /**
     * The open groups of a group plan's shape in the subject that this learner may join: a seat free, their age group,
     * and (for a learner under 18) a teacher cleared for minors.
     *
     * @return list<array<string, mixed>> as OpenGroups gives them
     */
    public function groups(string $subjectId, PlanOption $plan, ?string $ageGroup, bool $minor, string $timezone, ?string $teacherId = null, ?CarbonImmutable $now = null): array
    {
        $groups = array_values(array_filter(
            $this->groups->handle($subjectId, null, $ageGroup, $timezone, $teacherId === null ? null : [$teacherId], $now),
            fn (array $g) => $g['minutes'] === $plan->sessionMinutes && $g['per_week'] === $plan->sessionsPerWeek && $g['free'] > 0,
        ));
        if ($minor && $groups !== []) {
            $cleared = $this->minors->cleared(array_values(array_unique(array_column($groups, 'teacher_id'))));
            $groups = array_values(array_filter($groups, fn (array $g) => in_array($g['teacher_id'], $cleared, true)));
        }

        return $groups;
    }
}
