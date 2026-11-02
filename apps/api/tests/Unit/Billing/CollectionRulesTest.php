<?php

use Carbon\CarbonImmutable;
use Modules\Billing\Domain\Collections\CollectionRules as R;
use Modules\Billing\Domain\Renewals\RenewalPace;

/*
 * The collection timeline's rules, pure (docs/architecture/02, section 27, J5a):
 * which step an invoice waiting to be paid is owed, on the payer's clock.
 */

$at = fn (string $local, string $tz = 'Asia/Riyadh') => CarbonImmutable::parse($local, $tz)->utc();

it('reminds the day before, on the day and two days late, from nine on the payer\'s clock, once each', function () use ($at) {
    $issued = $at('2026-10-05 10:00');
    $due = $at('2026-10-08 10:00');

    expect(R::due($at('2026-10-06 12:00'), 'Asia/Riyadh', $issued, $due, []))->toBeNull()
        ->and(R::due($at('2026-10-07 08:59'), 'Asia/Riyadh', $issued, $due, []))->toBeNull()
        ->and(R::due($at('2026-10-07 09:00'), 'Asia/Riyadh', $issued, $due, []))->toBe(R::DUE_TOMORROW)
        ->and(R::due($at('2026-10-07 20:00'), 'Asia/Riyadh', $issued, $due, [R::DUE_TOMORROW => $at('2026-10-07 09:00')]))->toBeNull()
        ->and(R::due($at('2026-10-08 09:10'), 'Asia/Riyadh', $issued, $due, [R::DUE_TOMORROW => $at('2026-10-07 09:00')]))->toBe(R::DUE_TODAY)
        // Its moment passed: no "due today" (the overdue step comes two days later).
        ->and(R::due($at('2026-10-08 11:00'), 'Asia/Riyadh', $issued, $due, []))->toBeNull()
        ->and(R::due($at('2026-10-09 12:00'), 'Asia/Riyadh', $issued, $due, []))->toBeNull()
        ->and(R::due($at('2026-10-10 09:00'), 'Asia/Riyadh', $issued, $due, []))->toBe(R::OVERDUE)
        ->and(R::due($at('2026-10-11 09:00'), 'Asia/Riyadh', $issued, $due, [R::OVERDUE => $at('2026-10-10 09:00')]))->toBeNull()
        // Long overdue when this began: the office's, never a sudden message.
        ->and(R::due($at('2026-10-16 09:00'), 'Asia/Riyadh', $issued, $due, []))->toBeNull();
});

it('says nothing the day the invoice itself came out, and at most one step a day', function () use ($at) {
    $due = $at('2026-10-08 18:00');

    expect(R::due($at('2026-10-07 12:00'), 'Asia/Riyadh', $at('2026-10-07 10:00'), $due, []))->toBeNull()
        ->and(R::due($at('2026-10-08 12:00'), 'Asia/Riyadh', $at('2026-10-08 08:00'), $due, []))->toBeNull()
        ->and(R::due($at('2026-10-08 12:00'), 'Asia/Riyadh', $at('2026-10-05 08:00'), $due, [R::LESSON_AT_RISK => $at('2026-10-08 09:30')]))->toBeNull()
        // The office's pay link does not count as the day's step.
        ->and(R::due($at('2026-10-08 12:00'), 'Asia/Riyadh', $at('2026-10-05 08:00'), $due, [R::PAY_LINK => $at('2026-10-08 09:30')]))->toBe(R::DUE_TODAY);
});

it('warns a day before rule 16 would cancel the first unpaid lesson, whatever the hour, and first', function () use ($at) {
    $cancels = $at('2026-10-08 06:00');
    $issued = $at('2026-10-01 10:00');
    $due = $at('2026-10-08 06:00');

    expect(R::due($at('2026-10-07 05:59'), 'Asia/Riyadh', $issued, $due, [], $cancels))->toBeNull()
        ->and(R::due($at('2026-10-07 06:00'), 'Asia/Riyadh', $issued, $due, [], $cancels))->toBe(R::LESSON_AT_RISK)
        // It wins over "due tomorrow" the same day, and the day has had its step.
        ->and(R::due($at('2026-10-07 10:00'), 'Asia/Riyadh', $issued, $due, [], $cancels))->toBe(R::LESSON_AT_RISK)
        ->and(R::due($at('2026-10-07 10:00'), 'Asia/Riyadh', $issued, $due, [R::LESSON_AT_RISK => $at('2026-10-07 06:00')], $cancels))->toBeNull()
        // Too late to be of use in its last hour.
        ->and(R::due($at('2026-10-08 05:30'), 'Asia/Riyadh', $issued, $due, [], $cancels))->toBeNull();
});

it('counts days on the payer\'s calendar across a clock change', function () {
    // London leaves summer time on Sunday 25 October 2026: that Sunday has 25 hours.
    $tz = 'Europe/London';
    $issued = CarbonImmutable::parse('2026-10-20 10:00', $tz)->utc();
    $due = CarbonImmutable::parse('2026-10-26 08:00', $tz)->utc();

    expect(R::due(CarbonImmutable::parse('2026-10-25 09:00', $tz)->utc(), $tz, $issued, $due, []))->toBe(R::DUE_TOMORROW)
        ->and(R::due(CarbonImmutable::parse('2026-10-28 09:00', $tz)->utc(), $tz, $issued, $due, []))->toBe(R::OVERDUE)
        // 06:30 in London is 09:30 in Riyadh: the due day's reminder already goes there, not yet in London.
        ->and(R::due(CarbonImmutable::parse('2026-10-26 06:30', $tz)->utc(), 'Asia/Riyadh', $issued, $due, []))->toBe(R::DUE_TODAY)
        ->and(R::due(CarbonImmutable::parse('2026-10-26 06:30', $tz)->utc(), $tz, $issued, $due, []))->toBeNull();
});

it('previews a renewal one to three days ahead, and reckons the mark at the plan\'s pace', function () use ($at) {
    expect(R::previewDue(3, $at('2026-10-07 09:00'), 'Asia/Riyadh'))->toBeTrue()
        ->and(R::previewDue(4, $at('2026-10-07 09:00'), 'Asia/Riyadh'))->toBeFalse()
        ->and(R::previewDue(0, $at('2026-10-07 09:00'), 'Asia/Riyadh'))->toBeFalse()
        ->and(R::previewDue(2, $at('2026-10-07 08:00'), 'Asia/Riyadh'))->toBeFalse();

    // 8 lessons of 30 minutes left, renews at 2, twice a week: 6 lessons, three weeks.
    $pace = new RenewalPace(240, 60, 30, 2);
    expect($pace->daysUntilMark())->toBe(21)
        ->and($pace->lessonsLeft())->toBe(8)
        ->and($pace->markLessons())->toBe(2)
        ->and($pace->lessonsUntilMark())->toBe(6)
        ->and($pace->reachesMarkWithin(7))->toBeFalse()
        ->and((new RenewalPace(90, 60, 30, 2))->reachesMarkWithin(7))->toBeTrue()
        ->and((new RenewalPace(60, 60, 30, 2))->daysUntilMark())->toBe(0);
});
