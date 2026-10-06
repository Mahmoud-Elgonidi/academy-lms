<?php

use Modules\Billing\Domain\BlockTerms;
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
