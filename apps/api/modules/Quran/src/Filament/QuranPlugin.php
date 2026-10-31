<?php

namespace Modules\Quran\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Quran\Filament\Pages\HifzOverview;
use Modules\Quran\Filament\Pages\HifzStudent;
use Modules\Quran\Filament\Pages\QuranSettingsPage;

/** The Quran in the academy back office (its own menu group, gone while the plan leaves the Quran out): the hifz board, a student's hifz, the settings. */
final class QuranPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'quran';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([HifzOverview::class, HifzStudent::class, QuranSettingsPage::class]);
    }

    public function boot(Panel $panel): void {}
}
