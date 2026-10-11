<?php

namespace Modules\Platform\Filament\Console\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Modules\Platform\Actions\Games\OpenGameDraft;
use Modules\Platform\Actions\Games\SetGameStatus;
use Modules\Platform\Actions\Games\StartGameDraft;
use Modules\Platform\Games\SampleItems;
use Modules\Platform\Games\StudioStats;
use Modules\Platform\Live\GameStudioChanged;
use Modules\Platform\Models\Games\Game;
use Modules\Platform\Models\Games\GameCreature;
use Modules\Platform\Models\Games\GameDraft;
use Modules\Platform\Models\Games\GameIsland;
use Modules\SharedKernel\Games\ItemKind;

/**
 * The games studio (docs/architecture/02, section 18; the approved design,
 * Studio): how the games do across academies, the world's islands drawn as
 * the children see them, their creatures and the games on each (try one,
 * open its page, develop it into a new version, retire it or bring it back),
 * the islands' music and sounds, the drafts in progress, and a new game from
 * a story, which Claude designs.
 */
final class GameStudio extends Page
{
    protected string $view = 'platform::games.studio';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'games';

    public ?string $trying = null;

    public string $tryStage = 'kids';

    /** The island whose scene is open in the preview. */
    public ?string $previewing = null;

    public static function getNavigationLabel(): string
    {
        return __('platform::games.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::games.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::games.subtitle');
    }

    /** claude (a key and a model are set), demo (the local stand-in), or off. */
    public function designer(): string
    {
        if (config('platform.games.designer.driver') === 'demo' && ! app()->isProduction()) {
            return 'demo';
        }

        return (string) config('platform.games.designer.key') !== '' && (string) config('platform.games.designer.model') !== '' ? 'claude' : 'off';
    }

    public function channel(): string
    {
        return GameStudioChanged::CHANNEL;
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function drafts(): array
    {
        return GameDraft::query()->with(['island', 'game'])->whereIn('status', GameDraft::OPEN)->orderByDesc('updated_at')->get()
            ->map(fn (GameDraft $d) => [
                'id' => $d->id,
                'title' => $d->title() ?: ($d->game?->title() ?? __('platform::games.draft.new_game')),
                'island' => $d->island->emoji.' '.$d->island->name(),
                'status' => $d->status,
                'rounds' => $d->rounds,
                'story' => mb_strimwidth($d->story, 0, 140, '…'),
                'updated' => $d->updated_at->diffForHumans(),
                'url' => GameDraftPage::getUrl(['draft' => $d->id]),
            ])->all();
    }

    /**
     * The islands with their creatures and games (not "islands": Livewire's own).
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function world(): array
    {
        $games = Game::query()->with('current')->orderBy('sort')->orderBy('key')->get()->groupBy('island_id');
        $rounds = app(StudioStats::class)->roundsByGame();
        $out = GameIsland::query()->with('creatures')->orderBy('sort')->get()->map(fn (GameIsland $island) => [
            'id' => $island->id,
            'key' => $island->key,
            'music' => $island->music,
            'name' => $island->name(),
            'story' => app()->getLocale() === 'ar' ? $island->story_ar : $island->story_en,
            'emoji' => $island->emoji,
            'colors' => $island->colors,
            'creatures' => $island->creatures->map(fn (GameCreature $c) => ['emoji' => $c->emoji, 'name' => app()->getLocale() === 'ar' ? $c->name_ar : $c->name_en, 'rarity' => $c->rarity, 'look' => $c->look,
                'story' => app()->getLocale() === 'ar' ? $c->story_ar : $c->story_en])->all(),
            'games' => ($games[$island->id] ?? collect())->map(fn (Game $g) => [
                'id' => $g->id,
                'key' => $g->key,
                'title' => $g->title(),
                'summary' => app()->getLocale() === 'ar' ? $g->summary_ar : $g->summary_en,
                'status' => $g->status,
                'version' => $g->current?->version,
                'stages' => array_map(fn ($s) => __('shared::games.stages.'.$s), $g->stages),
                'kinds' => array_map(fn ($k) => ItemKind::tryFrom($k)?->label() ?? $k, $g->kinds),
                'rounds' => $rounds[$g->id] ?? 0,
                'url' => GameEditor::getUrl(['game' => $g->id]),
            ])->values()->all(),
        ])->all();

        return $out;
    }

    /** @return array{players: int, academies: int, rounds: int, per_player: float, finished: int, eggs: int, mastery_eggs: int} */
    #[Computed]
    public function stats(): array
    {
        return app(StudioStats::class)->overview();
    }

    public function preview(string $islandId): void
    {
        $this->previewing = $islandId;
        $this->dispatch('open-modal', id: 'island-preview');
    }

    /** The game being tried in the modal, at its published version, with sample items. */
    #[Computed]
    public function tried(): ?array
    {
        $game = $this->trying ? Game::query()->with('current')->find($this->trying) : null;
        if ($game === null || $game->current === null) {
            return null;
        }
        $manifest = $game->current->manifest;

        return [
            'title' => $game->title(),
            'src' => route('games.play', $game->current->id),
            'items' => SampleItems::of($manifest['kinds'] ?? $game->kinds, (int) ($manifest['max_items'] ?? 6)),
            'stages' => $manifest['stages'] ?? $game->stages,
        ];
    }

    public function tryGame(string $gameId): void
    {
        $this->trying = $gameId;
        $stages = $this->tried['stages'] ?? ['kids'];
        $this->tryStage = in_array($this->tryStage, $stages, true) ? $this->tryStage : ($stages[0] ?? 'kids');
        $this->dispatch('open-modal', id: 'game-try');
    }

    public function setTryStage(string $stage): void
    {
        $this->tryStage = in_array($stage, StartGameDraft::STAGES, true) ? $stage : 'kids';
    }

    public function develop(string $gameId): void
    {
        $draft = app(OpenGameDraft::class)->handle(Game::query()->findOrFail($gameId), (string) Filament::auth()->id());
        $this->redirect(GameDraftPage::getUrl(['draft' => $draft->id]), navigate: true);
    }

    public function setStatus(string $gameId, string $status): void
    {
        app(SetGameStatus::class)->handle(Game::query()->findOrFail($gameId), $status);
        Notification::make()->success()->title(__($status === 'live' ? 'platform::games.revived_done' : 'platform::games.retired_done'))->send();
        unset($this->world);
    }

    protected function getHeaderActions(): array
    {
        $l = 'platform::games.';

        return [
            Action::make('new')->label(__($l.'new.action'))->icon(Heroicon::Sparkles)
                ->modalHeading(__($l.'new.heading'))->modalWidth('3xl')
                ->modalSubmitActionLabel(__($l.'new.start'))
                ->schema([
                    Textarea::make('story')->label(__($l.'attributes.story'))->required()->rows(7)->minLength(20)->maxLength(4000)
                        ->placeholder(__($l.'new.story_placeholder'))->helperText(__($l.'new.story_hint')),
                    Grid::make(2)->schema([
                        Select::make('island_id')->label(__($l.'attributes.island'))->required()->native(false)
                            ->options(GameIsland::query()->orderBy('sort')->get()->mapWithKeys(fn (GameIsland $i) => [$i->id => $i->emoji.' '.$i->name()])->all()),
                        TextInput::make('topic')->label(__($l.'attributes.topic'))->maxLength(160)->placeholder(__($l.'new.topic_placeholder')),
                    ]),
                    Grid::make(2)->schema([
                        CheckboxList::make('stages')->label(__($l.'attributes.stages'))->required()
                            ->options(collect(StartGameDraft::STAGES)->mapWithKeys(fn ($s) => [$s => __('shared::games.stages.'.$s)])->all()),
                        CheckboxList::make('kinds')->label(__($l.'attributes.kinds'))->required()->helperText(__($l.'new.kinds_hint'))
                            ->options(collect(ItemKind::cases())->mapWithKeys(fn (ItemKind $k) => [$k->value => $k->label()])->all())
                            ->descriptions(collect(ItemKind::cases())->mapWithKeys(fn (ItemKind $k) => [$k->value => __('shared::games.kind_hints.'.$k->value)])->all()),
                    ]),
                ])
                ->fillForm(['stages' => ['kids'], 'kinds' => ['choice']])
                ->action(function (array $data) use ($l) {
                    $draft = app(StartGameDraft::class)->handle((string) Filament::auth()->id(), $data);
                    Notification::make()->success()->title(__($l.'new.started'))->send();
                    $this->redirect(GameDraftPage::getUrl(['draft' => $draft->id]), navigate: true);
                }),
        ];
    }
}
