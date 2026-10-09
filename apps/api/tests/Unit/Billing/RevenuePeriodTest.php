<?php

use Carbon\CarbonImmutable;
use Modules\Billing\Domain\Revenue\Split;
use Modules\Billing\Support\RevenuePeriod;

$days = fn (RevenuePeriod $p) => $p->first->toDateString().'..'.$p->last->toDateString();

it('shares a payment between courses in whole units that add up, the larger remainders first', function () {
    expect(Split::byWeight(100, [1, 1, 1]))->toBe([34, 33, 33])
        ->and(Split::byWeight(94000, ['karim' => 40000, 'nada' => 54000]))->toBe(['karim' => 40000, 'nada' => 54000])
        ->and(Split::byWeight(47000, ['karim' => 40000, 'nada' => 54000]))->toBe(['karim' => 20000, 'nada' => 27000])
        ->and(Split::byWeight(10001, ['a' => 1, 'b' => 2]))->toBe(['a' => 3334, 'b' => 6667])
        ->and(Split::byWeight(500, ['a' => 0, 'b' => 0]))->toBe(['a' => 500, 'b' => 0])
        ->and(array_sum(Split::byWeight(99999, [7, 13, 29, 51])))->toBe(99999)
        ->and(fn () => Split::byWeight(10, ['a' => -1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Split::byWeight(10, []))->toThrow(InvalidArgumentException::class);
});

it('reads periods on the academy\'s clock and compares them with the one before', function () use ($days) {
    // Friday 9 October 2026, 05:00 UTC = 08:00 in Riyadh.
    $now = CarbonImmutable::parse('2026-10-09 05:00', 'UTC');
    $make = fn (string $preset, ?string $from = null, ?string $to = null) => RevenuePeriod::make($preset, $from, $to, 'Asia/Riyadh', $now);

    expect($days($make('this_month')))->toBe('2026-10-01..2026-10-09')
        ->and($days($make('this_month')->previous()))->toBe('2026-09-01..2026-09-09')     // the same days of last month
        ->and($days($make('last_month')))->toBe('2026-09-01..2026-09-30')
        ->and($days($make('last_month')->previous()))->toBe('2026-08-01..2026-08-31')
        ->and($days($make('last_90')))->toBe('2026-07-12..2026-10-09')
        ->and($days($make('last_90')->previous()))->toBe('2026-04-13..2026-07-11')
        ->and($days($make('this_year')->previous()))->toBe('2025-01-01..2025-10-09')
        ->and($days($make('last_12')))->toBe('2025-11-01..2026-10-09')
        ->and($days($make('last_12')->previous()))->toBe('2024-11-01..2025-10-09')
        ->and($days($make('nonsense')))->toBe('2026-10-01..2026-10-09')
        ->and($make('this_month')->bounds())->toEqual([CarbonImmutable::parse('2026-09-30 21:00', 'UTC'), CarbonImmutable::parse('2026-10-09 21:00', 'UTC')]);

    // An evening in Riyadh that is already tomorrow there: 31 October 22:30 UTC is 1 November.
    $late = RevenuePeriod::make('this_month', null, null, 'Asia/Riyadh', CarbonImmutable::parse('2026-10-31 22:30', 'UTC'));
    expect($days($late))->toBe('2026-11-01..2026-11-01')->and($late->days())->toBe(1);

    // The end of a long month against a short one.
    $march = RevenuePeriod::make('this_month', null, null, 'Asia/Riyadh', CarbonImmutable::parse('2027-03-31 12:00', 'UTC'));
    expect($days($march->previous()))->toBe('2027-02-01..2027-02-28');
});

it('takes two days in any order, never past today nor longer than two years, and falls back to this month', function () use ($days) {
    $now = CarbonImmutable::parse('2026-10-09 05:00', 'UTC');
    $make = fn (?string $from, ?string $to) => RevenuePeriod::make('custom', $from, $to, 'Asia/Riyadh', $now);

    expect($days($make('2026-09-15', '2026-09-01')))->toBe('2026-09-01..2026-09-15')
        ->and($days($make('2026-09-15', '2026-09-01')->previous()))->toBe('2026-08-17..2026-08-31')
        ->and($days($make('2026-10-01', '2027-01-01')))->toBe('2026-10-01..2026-10-09')
        ->and($make('2020-01-01', '2026-10-09')->days())->toBe(RevenuePeriod::MAX_DAYS)
        ->and($make('2026-02-30', '2026-03-01')->preset)->toBe('this_month')
        ->and($make(null, '2026-03-01')->preset)->toBe('this_month');
});

it('groups the chart by day, by week from Saturday or by month, every day in one bar', function () {
    $now = CarbonImmutable::parse('2026-10-09 05:00', 'UTC');
    $month = RevenuePeriod::make('last_month', null, null, 'Asia/Riyadh', $now);
    $quarter = RevenuePeriod::make('last_90', null, null, 'Asia/Riyadh', $now);
    $year = RevenuePeriod::make('last_12', null, null, 'Asia/Riyadh', $now);

    expect($month->step())->toBe('day')->and(count($month->buckets()))->toBe(30)
        ->and($quarter->step())->toBe('week')
        ->and($year->step())->toBe('month')->and(count($year->buckets()))->toBe(12);

    $weeks = $quarter->buckets();
    foreach (array_slice($weeks, 1) as $week) {
        expect($week['first']->dayOfWeek)->toBe(CarbonImmutable::SATURDAY);
    }
    foreach (array_slice($weeks, 0, -1) as $week) {
        expect($week['last']->dayOfWeek)->toBe(CarbonImmutable::FRIDAY);
    }
    expect($weeks[0]['first']->toDateString())->toBe('2026-07-12')   // a Sunday: the first bar is a short week
        ->and(end($weeks)['last']->toDateString())->toBe('2026-10-09')
        ->and(count($quarter->dayIndex()))->toBe(90)
        ->and($quarter->dayIndex()['2026-07-17'])->toBe(0)
        ->and($quarter->dayIndex()['2026-07-18'])->toBe(1);
});

it('starts a day where the clock does when midnight does not exist (Cairo, the last Friday of April)', function () {
    $day = RevenuePeriod::make('custom', '2026-04-24', '2026-04-24', 'Africa/Cairo', CarbonImmutable::parse('2026-10-09 05:00', 'UTC'));

    // 00:00 jumps to 01:00 summer time (UTC+3): the day starts at 22:00 UTC the evening before, and is 23 hours long.
    expect($day->bounds())->toEqual([CarbonImmutable::parse('2026-04-23 22:00', 'UTC'), CarbonImmutable::parse('2026-04-24 21:00', 'UTC')]);
});
