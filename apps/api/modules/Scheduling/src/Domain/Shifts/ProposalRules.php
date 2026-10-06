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

        // Taking time away needs notice; only adding time can start tomorrow.
        [, $removed] = $proposed->addedAndRemovedMinutes($current);
        $earliest = $removed > 0 ? $today->addDays($this->reductionNoticeDays) : $today->addDay();
        if ($effectiveFrom->lessThan($earliest->startOfDay())) {
            $problems[] = $removed > 0
                ? ['notice_needed', ['days' => $this->reductionNoticeDays, 'date' => $earliest->toDateString()]]
                : ['starts_tomorrow_at_earliest', ['date' => $earliest->toDateString()]];
        }

        return $problems;
    }

    /** The earliest start date allowed for $proposed against $current. */
    public function earliestStart(WeeklyShifts $proposed, WeeklyShifts $current, CarbonImmutable $today): CarbonImmutable
    {
        [, $removed] = $proposed->addedAndRemovedMinutes($current);

        return ($removed > 0 ? $today->addDays($this->reductionNoticeDays) : $today->addDay())->startOfDay();
    }

    public static function hours(int $minutes): string
    {
        return rtrim(rtrim(number_format($minutes / 60, 1, '.', ''), '0'), '.');
    }
}
