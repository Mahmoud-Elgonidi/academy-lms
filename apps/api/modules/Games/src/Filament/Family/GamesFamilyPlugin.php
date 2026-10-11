<?php

namespace Modules\Games\Filament\Family;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** The games in the family area (/my): the learner's islands, quests and rounds. */
final class GamesFamilyPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'games-family';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([FamilyGames::class]);
    }

    public function boot(Panel $panel): void {}
}
