<?php

namespace Modules\Scheduling\Queries\Start;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Scheduling\Domain\Availability\Candidate;
use Modules\Scheduling\Domain\Availability\Matcher;
use Modules\Scheduling\Domain\Availability\StudentPreference;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Queries\AvailabilityQuery;
use Modules\Scheduling\Queries\Groups\OpenGroups;
use Modules\Scheduling\Support\MinorsGuard;

/**
 * The weekly times a family may start a course at with one teacher (docs/
 * architecture/02, section 27, J3), on the family's clock: the teacher's free
 * time (AvailabilityQuery: shifts less lessons, leave, holidays, the daily
 * cap and others' holds), each weekly time free in every one of the first
 * WEEKS weeks, on whole and half hours of the family's clock, and never when
 * the learner already has a lesson. The first lesson is far enough ahead for
 * the family to pay before an unpaid lesson would be cancelled (rule 16:
 * unpaid_cancel_hours, plus PAY_HOURS to pay in). A group plan's "times" are
 * the open groups of its shape instead (seats free, the learner's age, a
 * teacher cleared for a minor).
 */
final class StartSlots
{
    /** Each weekly time is free in all of the first this many weeks. */
    public const WEEKS = 4;

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
     * The teacher's weekly times for a private plan's lessons, on the family's clock, each free in all of the first WEEKS
     * weeks from the first day, and none when the learner is busy then. Keyed by Candidate::key() (the teacher's clock).
     *
     * @return array<string, Candidate>
     */
    public function private(object $teacher, string $learnerId, PlanOption $plan, string $timezone, ?string $viewerId, ?CarbonImmutable $now = null): array
    {
        $first = self::firstDay($timezone, $now);
        $from = $first->utc();
        $to = $from->addWeeks(self::WEEKS)->addDay();
        $free = $this->availability->forTeachers([(string) $teacher->id], $from, $to, $viewerId)[(string) $teacher->id] ?? null;
        if ($free === null) {
            return [];
        }
        $windows = array_map(fn (int $d) => ['weekday' => $d, 'start' => 0, 'end' => 24 * 60 + $plan->sessionMinutes], range(1, 7));
        $pref = new StudentPreference($timezone, $windows, max(1, $plan->sessionsPerWeek), $plan->sessionMinutes, $first, self::WEEKS, self::WEEKS);
        $busy = DB::table('session_participants')->where('user_id', $learnerId)->where('is_active', true)
            ->whereRaw('block_period && tstzrange(?, ?)', [$from, $to])
            ->selectRaw('extract(epoch from lower(block_period))::bigint as s, extract(epoch from upper(block_period))::bigint as e')->get();
        $duration = $plan->sessionMinutes * 60;

        $out = [];
        foreach ($this->matcher->candidates($free, $pref) as $candidate) {
            if (! in_array(substr($candidate->studentTime, 3, 2), ['00', '30'], true) || ! $candidate->full()) {
                continue;
            }
            foreach ($candidate->occurrences as $ts) {
                foreach ($busy as $b) {
                    if ($ts < (int) $b->e && $ts + $duration > (int) $b->s) {
                        continue 3;     // the learner has a lesson then
                    }
                }
            }
            $out[$candidate->key()] = $candidate;
        }

        return $out;
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
