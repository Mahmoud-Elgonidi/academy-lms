<?php

namespace Modules\People\Support;

use Locale;

/** Countries students live in, named in the current language (ISO 3166-1 alpha-2). */
final class Countries
{
    private const CODES = [
        'EG', 'SA', 'AE', 'KW', 'QA', 'BH', 'OM', 'JO', 'LB', 'SY', 'IQ', 'PS', 'YE', 'SD', 'LY', 'TN', 'DZ', 'MA', 'MR', 'SO',
        'TR', 'PK', 'IN', 'BD', 'ID', 'MY', 'SG', 'GB', 'IE', 'US', 'CA', 'AU', 'NZ', 'DE', 'FR', 'NL', 'BE', 'SE', 'NO', 'DK',
        'FI', 'CH', 'AT', 'IT', 'ES', 'PT', 'GR', 'ZA', 'NG', 'KE',
    ];

    /** @return array<string, string> code => name, by name */
    public static function options(): array
    {
        $names = [];
        foreach (self::CODES as $code) {
            $names[$code] = self::name($code);
        }
        asort($names);

        return $names;
    }

    public static function name(string $code): string
    {
        return Locale::getDisplayRegion('-'.$code, app()->getLocale()) ?: $code;
    }
}
