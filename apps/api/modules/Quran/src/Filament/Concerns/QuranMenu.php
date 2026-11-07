<?php

namespace Modules\Quran\Filament\Concerns;

use Modules\SharedKernel\Filament\NavGroup;
use UnitEnum;

/** The Quran's screens in the back office share one menu group. */
trait QuranMenu
{
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavGroup::Quran;
    }
}
