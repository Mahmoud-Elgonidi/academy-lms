<?php

namespace Modules\Games\Filament\Family;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

/**
 * The games in the family area (/my), on the learner's journey (Learning's page,
 * which renders two hooks by name): the rounds, their results and the eggs
 * ("learning::journey.play"), a guardian's limits and glance at the week
 * ("learning::journey.guardian"); and the album of creatures.
 */
final class GamesFamilyPlugin implements Plugin
{
    public const PLAY_HOOK = 'learning::journey.play';

    public const GUARDIAN_HOOK = 'learning::journey.guardian';

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
        $panel->pages([FamilyGames::class, FamilyCreatures::class]);
    }

    public function boot(Panel $panel): void {}

    /** Called by GamesServiceProvider: the journey's games components and their hooks, whichever panel boots first. */
    public static function registerComponents(): void
    {
        Livewire::component('games.journey-play', JourneyPlay::class);
        Livewire::component('games.journey-guardian', JourneyGuardian::class);
        FilamentView::registerRenderHook(self::PLAY_HOOK, fn (array $data = []) => Blade::render(
            '@livewire(\'games.journey-play\', [\'start\' => $start], key(\'games-journey-play\'))',
            ['start' => $data['start'] ?? null],
        ));
        FilamentView::registerRenderHook(self::GUARDIAN_HOOK, fn (array $data = []) => Blade::render(
            '@livewire(\'games.journey-guardian\', [], key(\'games-journey-guardian-\'.$learner))',
            ['learner' => $data['learner'] ?? ''],
        ));
    }
}
