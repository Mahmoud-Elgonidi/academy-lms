<?php

namespace Modules\Platform\Filament\Console;

use Carbon\CarbonImmutable;
use Modules\Platform\Models\Academy;
use Modules\SharedKernel\Money\Money;

/** Small formatting shared by the console screens. */
final class ConsoleView
{
    public static function name(Academy $academy): string
    {
        return app()->getLocale() === 'ar' ? $academy->name_ar : $academy->name_en;
    }

    public static function hours(int $minutes): string
    {
        return rtrim(rtrim(number_format($minutes / 60, 1), '0'), '.');
    }

    /** @param  array<string, int>  $received currency => minor units */
    public static function money(array $received): array
    {
        ksort($received);

        return array_values(array_map(fn (string $currency, int $minor) => Money::of($minor, $currency)->format(), array_keys($received), $received));
    }

    public static function ago(?string $at): ?string
    {
        return $at === null ? null : CarbonImmutable::parse($at)->locale(app()->getLocale())->diffForHumans();
    }

    public static function date(mixed $at, string $tz): string
    {
        return CarbonImmutable::parse($at)->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M Y');
    }
}
