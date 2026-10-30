<?php

namespace Modules\Scheduling\Domain\Shifts;

use Carbon\CarbonImmutable;

/**
 * What a proposal must satisfy to be submitted (docs/architecture/02, section
 * 2, "الـ validation وقت الإرسال"). Pure: returns problems as translation keys
 * with parameters, so the API and the back office show the same messages.
 */
final readonly class ProposalRules
{
    public function __construct(
        public int $minBlockMin = 60,
        public int $reductionNoticeDays = 14,
    ) {}

    /**
     * @param  CarbonImmutable  $today  today in the teacher's timezone
     * @param  bool  $byAcademy  the academy's own schedule for the teacher, which may start today and needs no notice
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    public function check(
        WeeklyShifts $proposed,
        CarbonImmutable $effectiveFrom,
        WeeklyShifts $current,
        CarbonImmutable $today,
        ?int $minWeekly = null,
        ?int $maxWeekly = null,
        ?int $contractMaxWeekly = null,
        bool $byAcademy = false,
    ): array {
        $problems = $proposed->problems($this->minBlockMin);

        if ($proposed->blocks === []) {
            $problems[] = ['empty', []];
        }

        $weekly = $proposed->weeklyMinutes();
        if ($minWeekly !== null && $maxWeekly !== null && $minWeekly > $maxWeekly) {
            $problems[] = ['range_inverted', []];
        }
        if ($maxWeekly !== null && $weekly > $maxWeekly) {
            $problems[] = ['above_own_max', ['hours' => self::hours($weekly), 'max' => self::hours($maxWeekly)]];
        }
        if ($contractMaxWeekly !== null && $weekly > $contractMaxWeekly) {
            $problems[] = ['above_contract', ['hours' => self::hours($weekly), 'max' => self::hours($contractMaxWeekly)]];
        }

        // The teacher's own: taking time away needs notice, and only adding time can start tomorrow.
        // The academy's may start today, either way.
        [, $removed] = $proposed->addedAndRemovedMinutes($current);
        $earliest = $this->earliestStart($proposed, $current, $today, $byAcademy);
        if ($effectiveFrom->lessThan($earliest)) {
            $problems[] = $removed > 0 && ! $byAcademy
                ? ['notice_needed', ['days' => $this->reductionNoticeDays, 'date' => $earliest->toDateString()]]
                : ['starts_tomorrow_at_earliest', ['date' => $earliest->toDateString()]];
        }

        return $problems;
    }

    /** The earliest start date allowed for $proposed against $current. */
    public function earliestStart(WeeklyShifts $proposed, WeeklyShifts $current, CarbonImmutable $today, bool $byAcademy = false): CarbonImmutable
    {
        if ($byAcademy) {
            return $today->startOfDay();
        }
        [, $removed] = $proposed->addedAndRemovedMinutes($current);

        return ($removed > 0 ? $today->addDays($this->reductionNoticeDays) : $today->addDay())->startOfDay();
    }

    public static function hours(int $minutes): string
    {
        return rtrim(rtrim(number_format($minutes / 60, 1, '.', ''), '0'), '.');
    }
}
