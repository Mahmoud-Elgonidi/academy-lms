<?php

namespace App\Filament\Family;

use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** The family area's home: the cards each module adds (next lessons, progress, homework, balance). */
final class FamilyHome extends Dashboard
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    public static function getNavigationLabel(): string
    {
        return __('family.home');
    }

    public function getTitle(): string|Htmlable
    {
        return __('family.home_title', ['name' => auth()->user()?->name]);
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 2];
    }
}
