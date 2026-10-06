<?php

namespace App\Filament\Family;

use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;

/**
 * The family area's home: the cards each module adds (the journey or the
 * progress, next lessons, homework, balance), in the viewer's look.
 */
final class FamilyHome extends Dashboard
{
    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return in_array(self::stage(), [LearnerStage::Kids, LearnerStage::Middle], true) ? Heroicon::OutlinedRocketLaunch : Heroicon::OutlinedHome;
    }

    public static function getNavigationLabel(): string
    {
        return __('family.home.'.self::stage()->value);
    }

    public function getTitle(): string|Htmlable
    {
        $name = (string) auth()->user()?->name;

        return __('family.home_title.'.self::stage()->value, ['name' => self::stage() === LearnerStage::Classic ? $name : strtok($name, ' ')]);
    }

    private static function stage(): LearnerStage
    {
        return app(FamilyView::class)->stage((string) auth()->id());
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 2];
    }
}
