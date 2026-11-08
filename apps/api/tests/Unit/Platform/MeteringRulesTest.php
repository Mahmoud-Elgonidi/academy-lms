<?php

use Carbon\CarbonImmutable;
use Modules\Platform\Domain\Metering\Burn;
use Modules\Platform\Domain\Metering\CreditRates;
use Modules\Platform\Domain\Metering\Entry;
use Modules\Platform\Domain\Metering\Hold;
use Modules\Platform\Domain\Metering\Ledger;
use Modules\Platform\Domain\Metering\Lot;
use Modules\Platform\Domain\Metering\Reservation;
use Modules\Platform\Domain\Metering\SpendOrder;
use Modules\Platform\Domain\Metering\Thresholds;
use Modules\Platform\Domain\Metering\VideoCharge;
use Modules\SharedKernel\Plans\RoomMinutes;

/*
 * The usage ledger's rules (docs/architecture/02, section 11): the month's
 * allowance first, then bought and granted lots ending soonest first; what
 * nothing covers is overage the next lot covers first; holds before a
 * request; AI credits by rate; a room's actual minutes with a cap; the pace.
 */

function meterLot(string $id, string $kind, int $left, string $ends, string $came = '2026-11-01 00:00'): Lot
{
    return new Lot($id, $kind, $left, CarbonImmutable::parse($ends), CarbonImmutable::parse($came));
}

it('spends the month\'s allowance first, then the lots that end soonest, never one that ended', function () {
    $at = CarbonImmutable::parse('2026-11-10 12:00');
    $lots = [
        meterLot('late', 'purchase', 100, '2027-09-01'),
        meterLot('soon', 'purchase', 50, '2027-02-01'),
        meterLot('bonus', 'bonus', 30, '2027-02-01', '2026-11-05'),
        meterLot('month', 'allowance', 20, '2026-12-01'),
        meterLot('gone', 'purchase', 999, '2026-11-09'),
        meterLot('empty', 'purchase', 0, '2027-01-01'),
    ];

    expect(array_map(fn (Lot $l) => $l->id, SpendOrder::of($lots, $at)))->toBe(['month', 'soon', 'bonus', 'late']);

    $draw = Ledger::draw($lots, 90, $at);
    expect($draw->fromLots)->toBe(['month' => 20, 'soon' => 50, 'bonus' => 20])->and($draw->overage)->toBe(0)->and($draw->total())->toBe(90);

    // Beyond everything: the rest is overage, owed.
    $all = Ledger::draw($lots, 250, $at);
    expect(array_sum($all->fromLots))->toBe(200)->and($all->overage)->toBe(50)
        ->and(Ledger::draw([], 5, $at)->overage)->toBe(5)
        ->and(fn () => Ledger::draw($lots, -1, $at))->toThrow(InvalidArgumentException::class);
});

it('works the balance out from the lots, the overage and the holds', function () {
    $at = CarbonImmutable::parse('2026-11-10');
    $balance = Ledger::balance([meterLot('m', 'allowance', 40, '2026-12-01'), meterLot('p', 'purchase', 100, '2027-11-01'), meterLot('x', 'purchase', 70, '2026-11-01')], 15, 10, $at);

    expect($balance->allowanceLeft)->toBe(40)->and($balance->bought)->toBe(100)->and($balance->debt)->toBe(15)->and($balance->held)->toBe(10)
        ->and($balance->available())->toBe(115)
        // The next lot covers the overage first, never more than it holds.
        ->and(Ledger::cover(15, 600))->toBe(15)->and(Ledger::cover(800, 600))->toBe(600)->and(Ledger::cover(0, 600))->toBe(0);
});

it('writes the statement\'s balance after each line, holds and uses without a limit leaving it as it was', function () {
    $after = Ledger::runningBalance([
        new Entry('allowance', 600, 'm'),
        new Entry('reserve', -5, null),
        new Entry('consume', -40, 'm'),
        new Entry('release', 5, null),
        new Entry('consume', -7, null, overage: true),
        new Entry('consume', -9, null),           // counted while the plan had no limit
        new Entry('purchase', 300, 'p'),
        new Entry('expire', -560, 'm'),
    ]);
    expect($after)->toBe([600, 600, 560, 560, 553, 553, 853, 293]);
});

it('holds every amount with the sign of its kind', function () {
    expect(Ledger::signFits('allowance', 10))->toBeTrue()->and(Ledger::signFits('allowance', -1))->toBeFalse()
        ->and(Ledger::signFits('consume', -3))->toBeTrue()->and(Ledger::signFits('consume', 3))->toBeFalse()
        ->and(Ledger::signFits('consume', 3, coveringOverage: true))->toBeTrue()
        ->and(Ledger::signFits('reserve', -2))->toBeTrue()->and(Ledger::signFits('release', 2))->toBeTrue()
        ->and(Ledger::signFits('expire', 0))->toBeFalse()->and(Ledger::signFits('adjust', -4))->toBeTrue()
        ->and(Ledger::signFits('nonsense', 1))->toBeFalse();
});

it('lets a request start only when its estimate fits, and lets go of a hold nobody settled', function () {
    $now = CarbonImmutable::parse('2026-11-10 12:00');
    $holds = [
        new Hold('a', 5, $now->subMinutes(2)),
        new Hold('b', 7, $now->subMinutes(Reservation::HOLD_MINUTES)),
        new Hold('c', 9, $now->subMinutes(1), released: true),
    ];

    expect(Reservation::held($holds, $now))->toBe(5)
        ->and(Reservation::stale($holds, $now))->toBe(['b'])
        ->and(Reservation::fits(5, 5, true))->toBeTrue()->and(Reservation::fits(4, 5, true))->toBeFalse()
        ->and(Reservation::fits(0, 50, false))->toBeTrue();
});

it('charges each helper its rate: a base, and the minutes of what it heard', function () {
    // practice 1, weekly-note wording 1, outline 5, recitation 2 a minute, report draft 2 and 1 a minute.
    expect(CreditRates::charge(1, 0))->toBe(1)
        ->and(CreditRates::charge(5, 0))->toBe(5)
        ->and(CreditRates::charge(0, 2, 59.0))->toBe(2)
        ->and(CreditRates::charge(0, 2, 61.0))->toBe(4)
        ->and(CreditRates::charge(2, 1, 150.0))->toBe(5)
        ->and(CreditRates::charge(2, 1, 0.4))->toBe(3)
        ->and(CreditRates::minutes(0.0))->toBe(1)
        ->and(CreditRates::least(0, 2))->toBe(2)->and(CreditRates::least(1, 0))->toBe(1)->and(CreditRates::least(0, 0))->toBe(1)
        // Before hearing: the size at a modest bitrate, never more than the cap.
        ->and(CreditRates::estimateMinutes(8000 * 90))->toBe(2)
        ->and(CreditRates::estimateMinutes(500 * 1024 * 1024))->toBe(CreditRates::MAX_ESTIMATE_MINUTES);
});

it('counts a room by its actual open time, rounded up, never more than the lesson and 15 minutes', function () {
    $start = CarbonImmutable::parse('2026-11-10 16:58:00');

    expect(VideoCharge::minutes($start, $start->addMinutes(31)->addSeconds(5), 30))->toBe(32)
        ->and(VideoCharge::minutes($start, $start->addHours(3), 30))->toBe(45)
        ->and(VideoCharge::minutes($start, $start, 30))->toBe(0)
        ->and(VideoCharge::minutes($start, $start->subMinute(), 30))->toBe(0)
        ->and(VideoCharge::delta(45, 30))->toBe(15)->and(VideoCharge::delta(30, 45))->toBe(0)
        // The classroom's running estimate uses the same margin.
        ->and(VideoCharge::MARGIN_MINUTES)->toBe(RoomMinutes::MARGIN_MINUTES);
});

it('tells the run-out from the last 14 days\' pace', function () {
    $daily = array_fill(0, 14, 10);

    expect(Burn::perDay($daily))->toBe(10.0)
        ->and(Burn::daysLeft(65, $daily))->toBe(6)
        ->and(Burn::daysLeft(100, array_fill(0, 14, 0)))->toBeNull()
        ->and(Burn::daysLeft(0, $daily))->toBe(0)
        ->and(Burn::perDay([14]))->toBe(1.0);
});

it('reaches each threshold once, and 50% only while the academy wants it', function () {
    expect(Thresholds::reached(50, 100, true))->toBe([50])
        ->and(Thresholds::reached(50, 100, false))->toBe([])
        ->and(Thresholds::reached(100, 100, true, [50]))->toBe([80, 100])
        ->and(Thresholds::reached(79, 100, true, [50]))->toBe([])
        ->and(Thresholds::reached(5, 0, true))->toBe([]);
});
