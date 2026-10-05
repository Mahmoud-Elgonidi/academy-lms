<?php

namespace Modules\Platform\Filament;

use Filament\Support\Colors\Color;

/**
 * A Filament palette (50–950) that keeps the academy's exact colour at shade
 * 600, the shade buttons and links use. Filament's own Color::hex() keeps only
 * the hue, so a deep green would come out as a bright teal.
 */
final class BrandPalette
{
    private const LIGHTNESS = [50 => 0.977, 100 => 0.950, 200 => 0.905, 300 => 0.840, 400 => 0.753, 500 => 0.683, 600 => 0.598, 700 => 0.515, 800 => 0.446, 900 => 0.395, 950 => 0.278];

    private const CHROMA = [50 => 0.0140, 100 => 0.0327, 200 => 0.0632, 300 => 0.1060, 400 => 0.1503, 500 => 0.1701, 600 => 0.1691, 700 => 0.1494, 800 => 0.1233, 900 => 0.0996, 950 => 0.0714];

    /** @return array<int, string> */
    public static function from(string $hex): array
    {
        [$l, $c, $h] = sscanf(Color::convertToOklch($hex), 'oklch(%f %f %f)');
        $darkest = min(self::LIGHTNESS[950], $l * 0.55);

        $palette = [];
        foreach (self::LIGHTNESS as $shade => $default) {
            $lightness = $shade <= 600
                ? $l + (($default - self::LIGHTNESS[600]) / (self::LIGHTNESS[50] - self::LIGHTNESS[600])) * (self::LIGHTNESS[50] - $l)
                : $l - ((self::LIGHTNESS[600] - $default) / (self::LIGHTNESS[600] - self::LIGHTNESS[950])) * ($l - $darkest);
            $chroma = $shade === 600 ? $c : min(0.37, $c * self::CHROMA[$shade] / self::CHROMA[600]);

            $palette[$shade] = sprintf('oklch(%.6f %.6f %.4f)', $lightness, $c < 0.02 ? 0 : $chroma, $h ?? 0);
        }

        return $palette;
    }
}
