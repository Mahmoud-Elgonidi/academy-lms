<?php

namespace Modules\Billing\Domain;

use InvalidArgumentException;

/**
 * VAT on an invoice, in minor units, rounded half up once on the total
 * (docs/architecture/03, "الضريبة"). Gulf consumer prices are usually shown
 * tax-inclusive, so the tax is taken out of the price; otherwise it is added.
 */
final class TaxCalculator
{
    /** @return array{subtotal: int, tax: int, total: int} */
    public static function compute(int $linesMinor, int $rateBp, bool $inclusive): array
    {
        if ($linesMinor < 0 || $rateBp < 0) {
            throw new InvalidArgumentException('Amounts and rates are not negative.');
        }

        if ($inclusive) {
            $tax = self::halfUp($linesMinor * $rateBp, 10_000 + $rateBp);

            return ['subtotal' => $linesMinor - $tax, 'tax' => $tax, 'total' => $linesMinor];
        }

        $tax = self::halfUp($linesMinor * $rateBp, 10_000);

        return ['subtotal' => $linesMinor, 'tax' => $tax, 'total' => $linesMinor + $tax];
    }

    private static function halfUp(int $numerator, int $denominator): int
    {
        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }
}
