<?php

namespace Modules\Platform\Filament\Console\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Modules\Platform\Actions\Games\DiscardGameDraft;
use Modules\Platform\Actions\Games\PublishGameDraft;
use Modules\Platform\Actions\Games\ReviseGameDraft;
use Modules\Platform\Actions\Games\StartGameDraft;
use Modules\Platform\Actions\Games\StopGameDesign;
use Modules\Platform\Games\SampleItems;
use Modules\Platform\Live\GameStudioChanged;
use Modules\Platform\Models\Games\GameDraft;
use Modules\Platform\Models\Games\GameDraftTurn;
use Modules\Platform\Models\Games\GameVersion;
use Modules\SharedKernel\Games\ItemKind;

/**
 * One draft in the studio: try the page as it stands (sample items, any
 * learner look), read what Claude said, send it changes (or the preview's
 * errors, or the package check's findings), and publish it as a new game or
 * the game's next version. The page follows the draft live: it updates by
 * itself when a round finishes.
 */
final class GameDraftPage extends Page
{
    protected string $view = 'platform::games.draft';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'games/drafts/{draft}';

    public string $draft;

    public string $stage = 'kids';

    public string $changes = '';

    public function mount(string $draft): void
    {
        abort_unless(Str::isUuid($draft) && GameDraft::query()->whereKey($draft)->exists(), 404);
        $this->draft = $draft;
        $this->stage = $this->record->stages[0] ?? 'kids';
    }

    #[Computed]
    public function record(): GameDraft
    {
        return GameDraft::query()->with(['island', 'game', 'turns'])->findOrFail($this->draft);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->title() ?: ($this->record->game?->title() ?? __('platform::games.draft.new_game'));
    }

    public function getSubheading(): string|Htmlable|null
    {
        $draft = $this->record;

        return $draft->game ? __('platform::games.draft.of_game', ['game' => $draft->game->title()]) : $draft->island->emoji.' '.$draft->island->name();
    }

    public function getBreadcrumbs(): array
    {
        return [GameStudio::getUrl() => __('platform::games.draft.back'), $this->getTitle()];
    }

    public function channel(): string
    {
        return GameStudioChanged::CHANNEL;
    }

    /** The preview: the draft's page (sandboxed) and sample items of its kinds. */
    #[Computed]
    public function preview(): ?array
    {
        $draft = $this->record;
        if ($draft->html === null) {
            return null;
        }
        $kinds = $draft->manifest['kinds'] ?? $draft->kinds;

        return [
            'src' => route('filament.console.games.draft-page', ['draft' => $draft->id, 'v' => substr(hash('sha256', $draft->html), 0, 12)]),
            'items' => SampleItems::of($kinds, (int) ($draft->manifest['max_items'] ?? 6)),
        ];
    }

    /** @return list<array{role: string, body: string, when: string}> */
    #[Computed]
    public function turns(): array
    {
        return $this->record->turns->map(fn (GameDraftTurn $t) => [
            'role' => $t->role,
            'body' => $t->body,
            'when' => $t->created_at->diffForHumans(),
        ])->all();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function facts(): array
    {
        $draft = $this->record;
        $manifest = $draft->manifest ?? [];
        $ar = app()->getLocale() === 'ar';

        return [
            'summary' => $ar ? $draft->summary_ar : $draft->summary_en,
            'how' => $manifest[$ar ? 'how_to_play_ar' : 'how_to_play_en'] ?? null,
            'stages' => array_map(fn ($s) => __('shared::games.stages.'.$s), $manifest['stages'] ?? $draft->stages),
            'kinds' => array_map(fn ($k) => ItemKind::tryFrom($k)?->label() ?? $k, $manifest['kinds'] ?? $draft->kinds),
            'min' => $manifest['min_items'] ?? null,
            'max' => $manifest['max_items'] ?? null,
            'model' => $draft->served_model,
            'tokens' => $draft->usage ? array_sum(array_intersect_key($draft->usage, ['input' => 1, 'output' => 1])) : null,
            'topic' => $draft->topic,
            'next' => $draft->game_id ? (int) GameVersion::query()->where('game_id', $draft->game_id)->max('version') + 1 : 1,
        ];
    }

    /** @return list<array{rule: string, label: string, detail: string}> */
    #[Computed]
    public function issues(): array
    {
        return array_map(fn (array $i) => $i + ['label' => __('platform::games.draft.rule.'.$i['rule'])], $this->record->issues ?? []);
    }

    public function failure(): ?string
    {
        $error = $this->record->error;
        if ($error === null) {
            return null;
        }
        $reason = Str::before($error, ':');

        return __('platform::games.failed.'.(in_array($reason, ['not_configured', 'refused', 'too_long', 'unreadable', 'unavailable'], true) ? $reason : 'unavailable'));
    }

    public function stale(): bool
    {
        $draft = $this->record;

        return $draft->status === 'designing' && $draft->updated_at->lt(now()->subMinutes(StopGameDesign::STALE_MINUTES));
    }

    public function setStage(string $stage): void
    {
        $this->stage = in_array($stage, StartGameDraft::STAGES, true) ? $stage : 'kids';
    }

    public function send(): void
    {
        app(ReviseGameDraft::class)->handle($this->record, (string) Filament::auth()->id(), $this->changes);
        $this->changes = '';
        Notification::make()->success()->title(__('platform::games.draft.sent'))->send();
        unset($this->record, $this->turns);
    }

    public function sendIssues(): void
    {
        $lines = array_map(fn (array $i) => '- '.$i['label'].': '.$i['detail'], $this->issues);
        $this->changes = __('platform::games.draft.issues_message')."\n".implode("\n", $lines);
        $this->send();
    }

    /** @param  list<string>  $errors  what the preview's page reported */
    public function sendErrors(array $errors): void
    {
        $errors = array_slice(array_values(array_unique(array_map(fn ($e) => mb_substr((string) $e, 0, 300), $errors))), 0, 10);
        if ($errors === []) {
            return;
        }
        $this->changes = __('platform::games.draft.errors_message')."\n- ".implode("\n- ", $errors);
        $this->send();
    }

    public function retry(): void
    {
        $this->changes = $this->changes !== '' ? $this->changes : __('platform::games.draft.retry_message');
        $this->send();
    }

    public function stop(): void
    {
        app(StopGameDesign::class)->handle($this->record);
        unset($this->record);
    }

    public function refreshDraft(): void
    {
        unset($this->record, $this->turns, $this->facts, $this->issues, $this->preview);
    }

    public function publishAction(): Action
    {
        $draft = $this->record;

        return Action::make('publish')
            ->label($draft->game_id ? __('platform::games.draft.publish_version', ['n' => $this->facts['next']]) : __('platform::games.draft.publish'))
            ->icon(Heroicon::RocketLaunch)
            ->disabled(fn () => $this->record->status !== 'ready' || $this->issues !== [])
            ->requiresConfirmation()
            ->modalDescription(__('platform::games.draft.publish_hint'))
            ->schema($draft->game_id ? [] : [
                TextInput::make('key')->label(__('platform::games.attributes.key'))->required()->maxLength(41)
                    ->regex('/^[a-z][a-z0-9-]{1,40}$/')->helperText(__('platform::games.draft.key_hint')),
            ])
            ->fillForm(fn () => ['key' => Str::slug((string) $this->record->title_en)])
            ->action(function (array $data) {
                app(PublishGameDraft::class)->handle($this->record, (string) Filament::auth()->id(), $data['key'] ?? null);
                Notification::make()->success()->title(__('platform::games.draft.published'))->send();
                $this->redirect(GameStudio::getUrl(), navigate: true);
            });
    }

    public function discardAction(): Action
    {
        return Action::make('discard')->label(__('platform::games.draft.discard'))->icon(Heroicon::OutlinedTrash)->color('gray')
            ->requiresConfirmation()->modalDescription(__('platform::games.draft.discard_confirm'))
            ->action(function () {
                app(DiscardGameDraft::class)->handle($this->record);
                Notification::make()->success()->title(__('platform::games.draft.discarded'))->send();
                $this->redirect(GameStudio::getUrl(), navigate: true);
            });
    }
}
