<?php

namespace Modules\Platform\Filament\Console\Pages;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Modules\Platform\Actions\Games\OpenGameDraft;
use Modules\Platform\Actions\Games\SaveGameDetails;
use Modules\Platform\Actions\Games\SetGameStatus;
use Modules\Platform\Actions\Games\StartGameDraft;
use Modules\Platform\Games\SampleItems;
use Modules\Platform\Games\StudioStats;
use Modules\Platform\Models\Games\Game;
use Modules\Platform\Models\Games\GameIsland;
use Modules\SharedKernel\Games\ItemKind;

/**
 * One game in the studio (the approved design, Studio-Game): what the catalog
 * says about it (names, what the child reads, its island and looks; saved by
 * SaveGameDetails), where its content comes from (every learner's own
 * lessons), its rules and rewards, a live preview with sample items, and how
 * it does across academies (StudioStats). Its page changes only as a new
 * version, developed with Claude in a draft.
 */
final class GameEditor extends Page
{
    protected string $view = 'platform::games.editor';

    protected static ?string $slug = 'games/{game}';

    protected static bool $shouldRegisterNavigation = false;

    public string $gameId;

    /** @var array{title_ar: string, title_en: string, summary_ar: string, summary_en: string, island_id: string, stages: list<string>} */
    public array $details = [];

    public string $tryStage = 'kids';

    public function mount(string $game): void
    {
        $model = Game::query()->findOrFail($game);
        $this->gameId = $model->id;
        $this->details = ['title_ar' => $model->title_ar, 'title_en' => $model->title_en, 'summary_ar' => $model->summary_ar, 'summary_en' => $model->summary_en,
            'island_id' => $model->island_id, 'stages' => $model->stages];
        $this->tryStage = $model->stages[0] ?? 'kids';
    }

    #[Computed]
    public function game(): Game
    {
        return Game::query()->with(['current', 'island'])->findOrFail($this->gameId);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->game->title();
    }

    public function getHeading(): string|Htmlable
    {
        return '';
    }

    /** @return array<string, string> island id => name */
    public function islands(): array
    {
        return GameIsland::query()->orderBy('sort')->get()->mapWithKeys(fn (GameIsland $i) => [$i->id => $i->emoji.' '.$i->name()])->all();
    }

    /** @return list<string> */
    public function stages(): array
    {
        return StartGameDraft::STAGES;
    }

    /** @return list<string> the kinds of practice it plays, as their names */
    public function kinds(): array
    {
        return array_map(fn ($k) => ItemKind::tryFrom($k)?->label() ?? $k, $this->game->kinds);
    }

    /** @return array{min: int, max: int} */
    public function rules(): array
    {
        $manifest = $this->game->current?->manifest ?? [];

        return ['min' => (int) ($manifest['min_items'] ?? 1), 'max' => (int) ($manifest['max_items'] ?? 6)];
    }

    /** @return list<array<string, mixed>> what a round looks like, with sample items */
    #[Computed]
    public function sample(): array
    {
        return SampleItems::of($this->game->kinds, $this->rules()['max']);
    }

    #[Computed]
    public function performance(): array
    {
        return app(StudioStats::class)->game($this->gameId);
    }

    public function setTryStage(string $stage): void
    {
        $this->tryStage = in_array($stage, StartGameDraft::STAGES, true) ? $stage : 'kids';
    }

    public function toggleStage(string $stage): void
    {
        $stages = $this->details['stages'];
        $this->details['stages'] = in_array($stage, $stages, true) ? array_values(array_diff($stages, [$stage])) : [...$stages, $stage];
    }

    public function save(): void
    {
        try {
            app(SaveGameDetails::class)->handle($this->game, $this->details);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => ['details.'.$k => $m])->all());
        }
        unset($this->game);
        Notification::make()->success()->title(__('platform::games.editor.saved'))->send();
    }

    public function develop(): void
    {
        $draft = app(OpenGameDraft::class)->handle($this->game, (string) Filament::auth()->id());
        $this->redirect(GameDraftPage::getUrl(['draft' => $draft->id]), navigate: true);
    }

    public function setStatus(string $status): void
    {
        app(SetGameStatus::class)->handle($this->game, $status);
        unset($this->game);
        Notification::make()->success()->title(__($status === 'live' ? 'platform::games.revived_done' : 'platform::games.retired_done'))->send();
    }
}
