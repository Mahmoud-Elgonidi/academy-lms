<?php

namespace Modules\Platform\Domain\Recharging;

/** The platform's receipt number: a year and its sequence, "R-2026-000123". Pure. */
final class ReceiptNumber
{
    public const PREFIX = 'R';

    public static function format(int $year, int $seq): string
    {
        return sprintf('%s-%04d-%06d', self::PREFIX, $year, $seq);
    }
}
