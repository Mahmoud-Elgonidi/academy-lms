<?php

namespace Modules\Games\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Modules\Games\Actions\SaveGameSettings;
use Modules\Games\Models\CreatureFind;
use Modules\Games\Models\GamePlay;
use Modules\Games\Models\GameSettings;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Games\CatalogGame;
use Modules\SharedKernel\Games\GameCatalog;
use Modules\SharedKernel\Games\ItemKind;

/**
 * The academy's games (curriculum.manage): on or off for its learners, the
 * minutes a learner may play a day, how much they played in the last month,
 * and the platform's games on offer with what each plays (so the academy
 * knows which practice to write in the curriculum).
 */
final class GameSettingsPage extends Page
{
    protected string $view = 'games::filament.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'games';

    public bool $enabled = true;

    public ?int $minutes = null;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageCurriculum->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('games::games.settings.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('games::games.settings.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('games::games.settings.subtitle');
    }

    public function mount(): void
    {
        $settings = GameSettings::current();
        $this->enabled = $settings->enabled;
        $this->minutes = $settings->daily_minutes;
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        app(SaveGameSettings::class)->handle((string) auth()->id(), $this->enabled, $this->minutes ?: null);
        Notification::make()->success()->title(__('games::games.settings.saved'))->send();
    }

    /** @return array{rounds: int, players: int, creatures: int} */
    #[Computed]
    public function stats(): array
    {
        $since = now()->subDays(30);

        return [
            'rounds' => GamePlay::query()->whereNotNull('finished_at')->where('finished_at', '>=', $since)->count(),
            'players' => GamePlay::query()->where('started_at', '>=', $since)->distinct()->count('student_id'),
            'creatures' => CreatureFind::query()->where('found_at', '>=', $since)->count(),
        ];
    }

    /** @return list<array{title: string, summary: string, island: string, kinds: list<string>, stages: list<string>}> */
    #[Computed]
    public function catalog(): array
    {
        $islands = collect(app(GameCatalog::class)->islands())->keyBy('key');

        return array_map(fn (CatalogGame $g) => [
            'title' => $g->title(),
            'summary' => $g->summary(),
            'island' => ($islands[$g->islandKey]?->emoji ?? '').' '.($islands[$g->islandKey]?->name() ?? ''),
            'kinds' => array_map(fn (ItemKind $k) => $k->label(), $g->kinds),
            'stages' => array_map(fn ($s) => __('shared::games.stages.'.$s), $g->stages),
        ], app(GameCatalog::class)->games());
    }
}
