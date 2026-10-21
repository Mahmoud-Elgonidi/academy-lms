<?php

namespace Modules\SharedKernel\Geo;

use Locale;

/**
 * The countries an academy's families usually live in (ISO 3166-1 alpha-2),
 * named in a language by the server's own ICU data: a price region says
 * which of them it serves, and the academy's page picks a visitor's prices
 * by it. Codes and names only, no database.
 */
final class Countries
{
    public const CODES = [
        'EG', 'SA', 'AE', 'KW', 'QA', 'BH', 'OM', 'JO', 'LB', 'SY', 'IQ', 'PS', 'YE', 'SD', 'LY', 'TN', 'DZ', 'MA', 'MR', 'SO',
        'TR', 'PK', 'IN', 'BD', 'ID', 'MY', 'SG', 'GB', 'IE', 'US', 'CA', 'AU', 'NZ', 'DE', 'FR', 'NL', 'BE', 'SE', 'NO', 'DK',
        'FI', 'CH', 'AT', 'IT', 'ES', 'PT', 'GR', 'ZA', 'NG', 'KE',
    ];

    public static function isKnown(mixed $code): bool
    {
        return is_string($code) && in_array($code, self::CODES, true);
    }

    /** @return array<string, string> code => name in this language, by name */
    public static function options(string $locale): array
    {
        $names = [];
        foreach (self::CODES as $code) {
            $names[$code] = self::name($code, $locale);
        }
        asort($names);

        return $names;
    }

    public static function name(string $code, string $locale): string
    {
        return Locale::getDisplayRegion('-'.$code, $locale) ?: $code;
    }
}
