<?php

use Modules\Billing\Domain\BlockTerms;
use Modules\Billing\Domain\Revenue\Split;
use Modules\Billing\Domain\TaxCalculator;
use Modules\SharedKernel\Money\Money;

it('takes VAT out of a tax-inclusive price, rounding half up once', function () {
    expect(TaxCalculator::compute(40000, 1500, true))->toBe(['subtotal' => 34783, 'tax' => 5217, 'total' => 40000])
        ->and(TaxCalculator::compute(115, 1500, true))->toBe(['subtotal' => 100, 'tax' => 15, 'total' => 115]);
});

it('adds VAT on top when prices exclude it, and charges none at zero', function () {
    expect(TaxCalculator::compute(10000, 500, false))->toBe(['subtotal' => 10000, 'tax' => 500, 'total' => 10500])
        ->and(TaxCalculator::compute(10000, 0, true))->toBe(['subtotal' => 10000, 'tax' => 0, 'total' => 10000]);
});

it('gives a block the weeks it needs plus two weeks of grace', function () {
    expect(BlockTerms::defaultValidityDays(8, 2))->toBe(42)
        ->and(BlockTerms::defaultValidityDays(4, 1))->toBe(42)
        ->and(BlockTerms::defaultValidityDays(12, 3))->toBe(42)
        ->and(BlockTerms::defaultValidityDays(8, 3))->toBe(35);
});

it('keeps money in minor units with each currency\'s own decimals', function () {
    expect(Money::parse('12.5', 'KWD')->minor)->toBe(12500)
        ->and(Money::parse('450', 'SAR')->minor)->toBe(45000)
        ->and(Money::parse('1200', 'EGP')->decimal())->toBe('1200.00')
        ->and(Money::of(12500, 'KWD')->decimal())->toBe('12.500')
        ->and(fn () => Money::of(1, 'SAR')->plus(Money::of(1, 'USD')))->toThrow(InvalidArgumentException::class);
});

it('formats with Latin digits in Arabic, isolated so a Latin symbol stays put', function () {
    $plain = fn (string $s) => str_replace("\u{00A0}", ' ', $s);

    expect($plain(Money::of(450000, 'SAR')->format('ar')))->toBe("\u{2068}4,500 ر.س.\u{2069}")
        ->and($plain(Money::of(8000, 'USD')->format('ar')))->toBe("\u{2068}80 US$\u{2069}")
        ->and(Money::of(12550, 'KWD')->format('en'))->toBe("KWD\u{00A0}12.550");
});

it('keeps the Gulf dinars to the fils and the yen whole, rounding what is typed half up', function () {
    $plain = fn (string $s) => str_replace("\u{00A0}", ' ', $s);

    foreach (['KWD', 'BHD', 'OMR', 'JOD'] as $dinar) {
        expect(Money::decimals($dinar))->toBe(3)
            ->and(Money::parse('12.345', $dinar)->minor)->toBe(12345)
            ->and(Money::parse('12.3455', $dinar)->minor)->toBe(12346)
            ->and(Money::of(5, $dinar)->decimal())->toBe('0.005');
    }
    expect($plain(Money::of(12345, 'BHD')->format('en')))->toBe('BHD 12.345')
        ->and($plain(Money::of(1000, 'OMR')->format('ar')))->toBe("\u{2068}1 ر.ع.\u{2069}")
        ->and(Money::decimals('JPY'))->toBe(0)
        ->and(Money::parse('150.5', 'JPY')->minor)->toBe(151)
        ->and(Money::of(1500, 'JPY')->decimal())->toBe('1500');
});

it('rounds VAT once to the fils, and shares dinars between courses in whole fils that add up', function () {
    // Bahrain's 10% inside 11.000 BHD is exactly 1.000; Oman's 5% on top of 12.345 OMR is 0.617 (617.25 fils).
    expect(TaxCalculator::compute(11000, 1000, true))->toBe(['subtotal' => 10000, 'tax' => 1000, 'total' => 11000])
        ->and(TaxCalculator::compute(12345, 500, false))->toBe(['subtotal' => 12345, 'tax' => 617, 'total' => 12962])
        ->and(TaxCalculator::compute(1, 1500, true))->toBe(['subtotal' => 1, 'tax' => 0, 'total' => 1]);

    foreach ([[7777, [12345, 11110]], [1, [3, 3, 3]], [100001, [12345, 0, 54321, 7]], [999, [1, 1, 1, 1, 1, 1, 1]]] as [$paid, $weights]) {
        $parts = Split::byWeight($paid, $weights);
        expect(array_sum($parts))->toBe($paid)
            ->and(min($parts))->toBeGreaterThanOrEqual(0);
    }
    expect(Split::byWeight(7777, ['ziad' => 12345, 'maryam' => 11110]))->toBe(['ziad' => 4093, 'maryam' => 3684]);
});
