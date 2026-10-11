<?php

namespace App\Filament\Family;

use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;

/**
 * The family area's home, in the viewer's look: a student's is their journey
 * (rounds, missions, the chest: the journey draws its own header); a
 * guardian's or an adult's has the cards each module adds (the journey's
 * card, next lessons, homework, balance).
 */
final class FamilyHome extends Dashboard
{
    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return self::stage() === LearnerStage::Classic ? Heroicon::OutlinedHome : Heroicon::OutlinedRocketLaunch;
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

    /** A student's journey draws its own header (their name, stars, rank). */
    public function getHeading(): string|Htmlable
    {
        return self::stage() === LearnerStage::Classic ? $this->getTitle() : '';
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
