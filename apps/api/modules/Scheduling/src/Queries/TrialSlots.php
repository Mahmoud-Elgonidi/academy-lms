<?php

namespace Modules\Scheduling\Queries;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Modules\Scheduling\Contracts\DTOs\TeacherSearch;
use Modules\Scheduling\Contracts\DTOs\TrialSlot;
use Modules\Scheduling\Contracts\DTOs\TrialSlotSearch;
use Modules\Scheduling\Domain\Availability\StudentPreference;
use Modules\Scheduling\Domain\Availability\TeacherAvailability;

/**
 * The starts a family may book a free trial at (docs/architecture/02,
 * section 27): Find a teacher's hard filters in trial mode (subject, age,
 * gender, accepts trials, cleared for minors), then each teacher's free time
 * (AvailabilityQuery: shifts less lessons, leave, holidays, daily caps and
 * holds), cut into starts on a half-hour grid of the family's clock, from
 * MIN_NOTICE_HOURS ahead. A start says how many teachers are free then,
 * never who.
 */
final class TrialSlots
{
    public const GRID_MINUTES = 30;

    public const MAX_SLOTS = 400;

    public function __construct(private readonly FindTeachersQuery $teachers) {}

    /** @return list<TrialSlot> */
    public function slots(TrialSlotSearch $search, ?CarbonImmutable $now = null): array
    {
        $byStart = [];
        foreach ($this->free($search, $now) as $teacherId => $starts) {
            foreach ($starts as $start) {
                $byStart[$start] = ($byStart[$start] ?? 0) + 1;
            }
        }
        ksort($byStart);
        $slots = [];
        foreach (array_slice($byStart, 0, self::MAX_SLOTS, true) as $start => $count) {
            $slots[] = new TrialSlot(CarbonImmutable::createFromTimestamp($start, 'UTC'), $count);
        }

        return $slots;
    }

    /**
     * The teachers free at exactly this start, least busy first.
     *
     * @return list<string>
     */
    public function freeAt(TrialSlotSearch $search, CarbonImmutable $startsAt, ?CarbonImmutable $now = null): array
    {
        $at = $startsAt->getTimestamp();
        $free = [];
        foreach ($this->free($search, $now, $busy) as $teacherId => $starts) {
            if (in_array($at, $starts, true)) {
                $free[$teacherId] = $busy[$teacherId];
            }
        }
        asort($free);

        return array_map('strval', array_keys($free));
    }

    /**
     * Each eligible teacher's starts (UTC seconds) over the search's days.
     *
     * @param  array<string, float>|null  $busy  filled with each teacher's share of shift time already taken
     * @return array<string, list<int>>
     */
    private function free(TrialSlotSearch $search, ?CarbonImmutable $now, ?array &$busy = null): array
    {
        $now ??= CarbonImmutable::now();
        $tz = $search->timezone;
        $firstDay = ($search->from ?? $now)->setTimezone($tz)->startOfDay();
        $earliest = max($now->addHours(max(1, $search->noticeHours))->getTimestamp(), $firstDay->getTimestamp());
        $last = $firstDay->addDays(max(1, min($search->days, 28)))->getTimestamp();
        $duration = $search->durationMin * 60;

        [$teachers, $availability] = $this->teachers->eligible(new TeacherSearch(
            subjectId: $search->subjectId,
            preference: new StudentPreference($tz, [], 1, $search->durationMin, $firstDay, (int) ceil(max(1, $search->days) / 7) + 1, 1),
            gender: match ($search->teacherGender) {
                'female' => 'female_only',
                'male' => 'male_only',
                default => 'any',
            },
            forTrial: true,
            ageGroup: $search->ageGroup,
            forMinor: $search->forMinor,
        ), $search->teacherId !== null ? [$search->teacherId] : null);

        $zone = new DateTimeZone($tz);
        $grid = self::GRID_MINUTES * 60;
        $out = [];
        $busy = [];
        foreach ($availability as $teacherId => $teacher) {
            /** @var TeacherAvailability $teacher */
            if ($search->excludeTeacherId !== null && (string) $teacherId === $search->excludeTeacherId) {
                continue;       // a second trial is with another teacher
            }
            $busy[$teacherId] = $teacher->utilisation();
            $starts = [];
            foreach ($teacher->free->intervals as [$start, $end]) {
                $t = max($start, $earliest);
                // Onto the grid of the family's clock (a zone half an hour off keeps its own half hours).
                $offset = $zone->getOffset(new \DateTimeImmutable('@'.$t));
                $rem = (($t + $offset) % $grid + $grid) % $grid;
                if ($rem !== 0) {
                    $t += $grid - $rem;
                }
                for (; $t + $duration <= $end && $t < $last; $t += $grid) {
                    $starts[] = $t;
                }
            }
            if ($starts !== [] && isset($teachers[$teacherId])) {
                $out[(string) $teacherId] = $starts;
            }
        }

        return $out;
    }
}
