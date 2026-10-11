<?php

namespace Modules\Games\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Games\Filament\Pages\GameSettingsPage;

/** The academy's games: on or off, minutes a day, and the games on offer. */
final class GamesPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'games';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([GameSettingsPage::class]);
    }

    public function boot(Panel $panel): void {}
}
