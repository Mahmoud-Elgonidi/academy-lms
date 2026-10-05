<?php

namespace Modules\Platform\Branding;

/**
 * Fonts an academy may pick: Google Fonts families with full Arabic support,
 * each with the weights it actually ships (asking for a missing weight makes
 * Google Fonts reject the whole stylesheet).
 */
final class BrandFonts
{
    /** @var array<string, list<int>> */
    public const ALL = [
        'IBM Plex Sans Arabic' => [400, 500, 600, 700],
        'Readex Pro' => [400, 500, 600, 700],
        'Cairo' => [400, 500, 600, 700],
        'Tajawal' => [400, 500, 700],
        'Almarai' => [400, 700],
        'Noto Kufi Arabic' => [400, 500, 600, 700],
        'Noto Sans Arabic' => [400, 500, 600, 700],
        'El Messiri' => [400, 500, 600, 700],
        'Changa' => [400, 500, 600, 700],
        'Rubik' => [400, 500, 600, 700],
    ];

    public static function has(string $family): bool
    {
        return array_key_exists($family, self::ALL);
    }

    /** @return array<string, string> family => family, for select inputs */
    public static function options(): array
    {
        return array_combine(array_keys(self::ALL), array_keys(self::ALL));
    }

    /** One stylesheet URL for every family given (duplicates removed). */
    public static function stylesheetUrl(string ...$families): string
    {
        $query = collect(array_unique($families))
            ->filter(fn (string $family) => self::has($family))
            ->map(fn (string $family) => 'family='.str_replace(' ', '+', $family).':wght@'.implode(';', self::ALL[$family]))
            ->implode('&');

        return "https://fonts.googleapis.com/css2?{$query}&display=swap";
    }
}
