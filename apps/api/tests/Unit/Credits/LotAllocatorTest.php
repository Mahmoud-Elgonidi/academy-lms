<?php

use Carbon\CarbonImmutable;
use Modules\Credits\Domain\LotAllocator;
use Modules\Credits\Domain\LotBalance;

function lot(string $id, int $minutes, ?string $expires, string $source = 'purchase', string $created = '2026-10-01'): LotBalance
{
    return new LotBalance($id, $source, $minutes, $expires ? CarbonImmutable::parse($expires) : null, CarbonImmutable::parse($created));
}

it('takes the minutes about to lapse first, and may split a session across lots', function () {
    $lots = [lot('later', 100, '2026-12-01'), lot('soon', 20, '2026-10-20'), lot('never', 100, null)];

    expect(LotAllocator::allocate($lots, 30, CarbonImmutable::parse('2026-10-10')))->toBe(['soon' => 20, 'later' => 10]);
});

it('only uses lots still valid when the session starts', function () {
    $lots = [lot('soon', 60, '2026-10-20'), lot('later', 60, '2026-12-01')];

    expect(LotAllocator::allocate($lots, 30, CarbonImmutable::parse('2026-10-25')))->toBe(['later' => 30])
        ->and(LotAllocator::allocate([lot('soon', 60, '2026-10-20')], 30, CarbonImmutable::parse('2026-10-25')))->toBeNull();
});

it('takes nothing when the lots cannot cover the whole session', function () {
    expect(LotAllocator::allocate([lot('a', 20, null), lot('b', 5, null)], 30, CarbonImmutable::parse('2026-10-10')))->toBeNull();
});

it('keeps makeup minutes for makeup sessions, which use nothing else', function () {
    $lots = [lot('paid', 60, '2026-12-01'), lot('makeup', 30, '2026-11-01', 'makeup')];
    $at = CarbonImmutable::parse('2026-10-10');

    expect(LotAllocator::allocate($lots, 30, $at))->toBe(['paid' => 30])
        ->and(LotAllocator::allocate($lots, 30, $at, forMakeup: true))->toBe(['makeup' => 30])
        ->and(LotAllocator::allocate([lot('makeup', 30, '2026-11-01', 'makeup')], 30, $at))->toBeNull()
        ->and(LotAllocator::allocate([lot('paid', 60, '2026-12-01')], 30, $at, forMakeup: true))->toBeNull();
});
