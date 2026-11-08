<?php

namespace Modules\Platform\Filament\Console\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;
use Modules\Platform\Actions\Ai\ImportAiEngines;
use Modules\Platform\Actions\Ai\SaveAiEngine;
use Modules\Platform\Ai\Engines\EngineKind;
use Modules\Platform\Ai\Engines\EngineProbe;
use Modules\Platform\Ai\Engines\EngineSpec;
use Modules\Platform\Ai\ModelCatalog;
use Modules\Platform\Health\SystemHealth;
use Modules\Platform\Models\Ai\AiEngine;
use Modules\Platform\Models\Ai\AiRoute;
use Modules\Platform\Models\PlatformAdmin;

/**
 * Console › AI engines (owners only, like Platform settings): every place a
 * model answers for the platform (Claude, an OpenAI-compatible server such
 * as OpenAI, vLLM or Ollama's /v1, Gemini, Ollama, ElevenLabs, a
 * Whisper-compatible speech server), each with its address, key, models,
 * time-out and cost, how it answered lately (ServiceHealth, its own check
 * on the health page) and how many routes use it. A saved key is never shown
 * again: blank keeps it, "remove the key" takes it away. "Try it" on a card
 * tries what is saved and is written to the health page; "Try what is typed"
 * in the form tries the form's values and is written nowhere. Models are
 * offered from the provider's own list where it has one (ModelCatalog),
 * typed otherwise. With no engines yet, the gateway runs on the server's
 * settings as before, and "Import the current settings" makes the engines
 * and default routes that keep it so.
 */
final class AiEnginesPage extends Page
{
    protected string $view = 'platform::console.ai-engines';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 31;

    protected static ?string $slug = 'ai/engines';

    public static function canAccess(): bool
    {
        $admin = auth('platform')->user();

        return $admin instanceof PlatformAdmin && $admin->is_owner && $admin->is_active;
    }

    public static function getNavigationLabel(): string
    {
        return __('platform::engines.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::engines.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::engines.subtitle');
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function engines(): array
    {
        $used = [];
        foreach (AiRoute::query()->get(['chain', 'split_chain']) as $route) {
            foreach ([...$route->chain, ...($route->split_chain ?? [])] as $step) {
                $used[$step['engine'] ?? ''] = ($used[$step['engine'] ?? ''] ?? 0) + 1;
            }
        }
        $health = app(SystemHealth::class);

        return AiEngine::query()->orderByDesc('enabled')->orderBy('name')->get()->map(function (AiEngine $e) use ($used, $health) {
            $kind = $e->kindOf();
            $check = $kind === EngineKind::Demo ? null : $health->service($e->service());

            return [
                'id' => $e->id,
                'name' => $e->name,
                'kind' => $kind->label(),
                'does' => array_values(array_filter([$kind->text() ? __('platform::routes.kinds.text') : null, $kind->speech() ? __('platform::routes.kinds.speech') : null])),
                'enabled' => $e->enabled,
                'host' => (string) (parse_url((string) ($e->base_url ?: $kind->defaultBase()), PHP_URL_HOST) ?: '—'),
                'key' => $e->hasKey(),
                'models' => array_values(array_filter([
                    $e->model_best ? __('platform::engines.card.best', ['model' => $e->model_best]) : null,
                    $e->model_fast ? __('platform::engines.card.fast', ['model' => $e->model_fast]) : null,
                    $e->model_speech ? __('platform::engines.card.speech', ['model' => $e->model_speech]) : null,
                ])),
                'timeout' => $e->timeout_seconds,
                'cost' => self::costLine($e),
                'routes' => (int) ($used[$e->id] ?? 0),
                'status' => $check['status'] ?? 'off',
                'health' => $check['value'] ?? __('platform::engines.card.demo'),
                'hint' => $check['hint'] ?? null,
                'notes' => $e->notes,
                'updated' => $e->updated_at?->diffForHumans(),
            ];
        })->all();
    }

    private static function costLine(AiEngine $e): ?string
    {
        $c = $e->cost_currency;
        $parts = array_filter([
            $e->cost_input_per_m !== null ? __('platform::engines.cost.input', ['n' => (float) $e->cost_input_per_m, 'c' => $c]) : null,
            $e->cost_output_per_m !== null ? __('platform::engines.cost.output', ['n' => (float) $e->cost_output_per_m, 'c' => $c]) : null,
            $e->cost_audio_per_min !== null ? __('platform::engines.cost.audio', ['n' => (float) $e->cost_audio_per_min, 'c' => $c]) : null,
            $e->cost_gpu_per_min !== null ? __('platform::engines.cost.gpu', ['n' => (float) $e->cost_gpu_per_min, 'c' => $c]) : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')->label(__('platform::engines.import.label'))->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->visible(fn () => ! AiEngine::query()->exists())
                ->requiresConfirmation()->modalHeading(__('platform::engines.import.label'))->modalDescription(__('platform::engines.import.about'))
                ->action(function () {
                    $made = app(ImportAiEngines::class)->handle($this->admin());
                    Notification::make()->success()->title(trans_choice('platform::engines.import.done', count($made), ['count' => count($made)]))->send();
                    unset($this->engines);
                }),
            Action::make('new')->label(__('platform::engines.new'))->icon(Heroicon::Plus)
                ->modalHeading(__('platform::engines.new'))->modalWidth('3xl')
                ->schema(fn () => $this->form(null))
                ->fillForm(['kind' => EngineKind::OpenAiCompatible->value, 'enabled' => true, 'cost_currency' => 'USD'])
                ->action(fn (array $data) => $this->save(null, $data)),
        ];
    }

    public function editAction(): Action
    {
        return Action::make('edit')->label(__('platform::engines.edit'))->icon(Heroicon::OutlinedPencilSquare)->color('gray')->size('sm')
            ->modalHeading(fn (array $arguments) => __('platform::engines.edit').' · '.AiEngine::query()->findOrFail($arguments['engine'])->name)
            ->modalWidth('3xl')
            ->schema(fn (array $arguments) => $this->form(AiEngine::query()->findOrFail($arguments['engine'])))
            ->fillForm(function (array $arguments) {
                $e = AiEngine::query()->findOrFail($arguments['engine']);

                return $e->only(['name', 'kind', 'base_url', 'model_best', 'model_fast', 'model_speech', 'timeout_seconds', 'cost_input_per_m', 'cost_output_per_m',
                    'cost_audio_per_min', 'cost_gpu_per_min', 'cost_currency', 'enabled', 'notes']) + ['zero_retention' => (bool) ($e->options['zero_retention'] ?? false), 'api_key' => null];
            })
            ->action(fn (array $data, array $arguments) => $this->save($arguments['engine'], $data));
    }

    /** "Try it" on a card: the engine as saved, written to its check on the health page. */
    public function tryAction(): Action
    {
        return Action::make('try')->label(__('platform::engines.try.label'))->icon('heroicon-m-signal')->color('gray')->size('sm')
            ->action(function (array $arguments) {
                $this->say(app(EngineProbe::class)->try(EngineSpec::of(AiEngine::query()->findOrFail($arguments['engine'])), record: true));
                unset($this->engines);
            });
    }

    /** @return list<mixed> */
    private function form(?AiEngine $engine): array
    {
        $f = 'platform::engines.fields.';
        $h = 'platform::engines.help.';
        $kind = fn (Get $get): ?EngineKind => EngineKind::tryFrom((string) ($engine?->kind ?? $get('kind')));
        $model = fn (string $name) => TextInput::make($name)->label(__($f.$name))->maxLength(120)
            ->datalist(fn (Get $get) => $this->modelOptions($kind($get), $get))
            ->visible(fn (Get $get) => $kind($get)?->text() ?? false);

        return [
            Callout::make(__('platform::engines.own_engine'))->color('gray')->columnSpanFull()
                ->visible(fn (Get $get) => in_array($kind($get), [EngineKind::OpenAiCompatible, EngineKind::Ollama, EngineKind::WhisperCompatible], true)),
            Grid::make(2)->schema([
                TextInput::make('name')->label(__($f.'name'))->required()->maxLength(80),
                Select::make('kind')->label(__($f.'kind'))->options(EngineKind::options())->required()->native(false)->live()
                    ->disabled($engine !== null)->helperText($engine !== null ? __($h.'kind_fixed') : null),
                TextInput::make('base_url')->label(__($f.'base_url'))->url()->maxLength(300)->columnSpanFull()
                    ->placeholder(fn (Get $get) => $kind($get)?->defaultBase() ?: 'https://gpu.example.com/v1')
                    ->helperText(fn (Get $get) => __($h.'base_url.'.($kind($get)?->value ?? 'openai_compatible')))
                    ->required(fn (Get $get) => $kind($get)?->needsUrl() ?? false)
                    ->visible(fn (Get $get) => $kind($get) !== EngineKind::Demo),
                TextInput::make('api_key')->label(__($f.'api_key'))->password()->autocomplete('new-password')->maxLength(2000)
                    ->placeholder($engine?->hasKey() ? __('platform::settings.secret_kept') : null)
                    ->helperText(fn (Get $get) => __($h.(($kind($get)?->needsKey() ?? true) ? 'api_key' : 'api_key_optional')))
                    ->visible(fn (Get $get) => $kind($get) !== EngineKind::Demo),
                Checkbox::make('clear_key')->label(__($f.'clear_key'))->visible($engine?->hasKey() ?? false),
                $model('model_best')->helperText(__($h.'model_best'))
                    ->hintAction(Action::make('reloadModels')->icon('heroicon-m-arrow-path')->label(__('platform::settings.models.reload'))
                        ->visible(fn (Get $get) => $kind($get)?->provider() !== null)
                        ->action(fn (Get $get) => $this->reloadModels($kind($get), $get))),
                $model('model_fast')->helperText(__($h.'model_fast')),
                TextInput::make('model_speech')->label(__($f.'model_speech'))->maxLength(120)
                    ->placeholder(fn (Get $get) => $kind($get) === EngineKind::ElevenLabs ? 'scribe_v2' : 'whisper-1')
                    ->visible(fn (Get $get) => ($kind($get)?->speech() ?? false) && $kind($get) !== EngineKind::Demo),
                Toggle::make('zero_retention')->label(__($f.'zero_retention'))->helperText(__($h.'zero_retention'))
                    ->visible(fn (Get $get) => $kind($get) === EngineKind::ElevenLabs),
                TextInput::make('timeout_seconds')->label(__($f.'timeout_seconds'))->numeric()->integer()->minValue(1)->maxValue(600)
                    ->placeholder((string) config('platform.ai.timeout'))->helperText(__($h.'timeout')),
            ]),
            Section::make(__('platform::engines.cost.title'))->description(__('platform::engines.cost.about'))->compact()->collapsible()->schema([
                Grid::make(3)->schema([
                    TextInput::make('cost_input_per_m')->label(__($f.'cost_input_per_m'))->numeric()->minValue(0),
                    TextInput::make('cost_output_per_m')->label(__($f.'cost_output_per_m'))->numeric()->minValue(0),
                    TextInput::make('cost_currency')->label(__($f.'cost_currency'))->maxLength(3)->regex('/^[A-Z]{3}$/'),
                    TextInput::make('cost_audio_per_min')->label(__($f.'cost_audio_per_min'))->numeric()->minValue(0),
                    TextInput::make('cost_gpu_per_min')->label(__($f.'cost_gpu_per_min'))->numeric()->minValue(0)->helperText(__($h.'gpu')),
                ]),
            ]),
            Toggle::make('enabled')->label(__($f.'enabled'))->helperText(__($h.'enabled')),
            Textarea::make('notes')->label(__($f.'notes'))->rows(2)->maxLength(1000),
            Actions::make([
                Action::make('tryTyped')->label(__('platform::engines.try.typed'))->icon('heroicon-m-signal')->color('gray')
                    ->action(fn (Get $get) => $this->tryTyped($engine, $get)),
            ]),
        ];
    }

    /** @return list<string> the models to choose from: the provider's list kept for this key, else the ones known here */
    private function modelOptions(?EngineKind $kind, Get $get): array
    {
        $provider = $kind?->provider();
        if ($provider === null) {
            return [];
        }
        [$key, $base] = $this->typedCredentials($kind, $get);
        $list = $key === '' && $base === '' ? null : app(ModelCatalog::class)->cached($provider, $key, $base);

        return array_keys($list !== null && $list->options !== [] ? $list->options : ModelCatalog::known($provider));
    }

    private function reloadModels(?EngineKind $kind, Get $get): void
    {
        $provider = $kind?->provider();
        if ($provider === null || RateLimiter::tooManyAttempts('ai-engine-models:'.$this->admin()->id, 10)) {
            return;
        }
        RateLimiter::hit('ai-engine-models:'.$this->admin()->id, 60);
        [$key, $base] = $this->typedCredentials($kind, $get);
        $catalog = app(ModelCatalog::class);
        $catalog->forget($provider, $key, $base);
        $list = $catalog->list($provider, $key, $base);
        $list->problem === null
            ? Notification::make()->success()->title(trans_choice('platform::engines.models_found', count($list->options), ['count' => count($list->options)]))->send()
            : Notification::make()->warning()->title(__('platform::services.problems.'.$list->problem->value, ['service' => (string) $get('name')]))->send();
    }

    /** @return array{0: string, 1: string} the key and address to ask with: what was typed, else the engine's saved key */
    private function typedCredentials(?EngineKind $kind, Get $get): array
    {
        $key = trim((string) $get('api_key'));
        if ($key === '' && ($id = $this->mountedEngineId()) !== null) {
            $key = (string) AiEngine::query()->find($id)?->api_key;
        }
        $base = rtrim(trim((string) ($get('base_url') ?: $kind?->defaultBase())), '/');

        return [$key, $base];
    }

    private function mountedEngineId(): ?string
    {
        foreach ($this->mountedActions as $mounted) {
            if (($mounted['name'] ?? null) === 'edit') {
                return $mounted['arguments']['engine'] ?? null;
            }
        }

        return null;
    }

    /** "Try what is typed": the form's values (a blank key: the saved one), never written to the health page. */
    private function tryTyped(?AiEngine $engine, Get $get): void
    {
        $kind = EngineKind::tryFrom((string) ($engine?->kind ?? $get('kind')));
        if ($kind === null || RateLimiter::tooManyAttempts('ai-engine-try:'.$this->admin()->id, 10)) {
            return;
        }
        RateLimiter::hit('ai-engine-try:'.$this->admin()->id, 60);
        $key = trim((string) $get('api_key'));
        $spec = new EngineSpec(
            id: null,
            name: trim((string) $get('name')) ?: $kind->label(),
            kind: $kind,
            baseUrl: rtrim(trim((string) ($get('base_url') ?: $kind->defaultBase())), '/'),
            key: $key !== '' ? $key : ($get('clear_key') ? '' : trim((string) $engine?->api_key)),
            modelBest: trim((string) $get('model_best')),
            modelFast: trim((string) $get('model_fast')),
            modelSpeech: trim((string) $get('model_speech')),
        );
        $this->say(app(EngineProbe::class)->try($spec));
    }

    /** @param  list<array{level: string, text: string}>  $lines */
    private function say(array $lines): void
    {
        $worst = collect($lines)->contains(fn ($l) => $l['level'] === 'fail') ? 'danger' : 'success';
        $body = implode('', array_map(fn ($l) => '<span style="display:block">'.e($l['text']).'</span>', $lines));
        Notification::make()->status($worst)->title(__('platform::engines.try.'.($worst === 'danger' ? 'failed' : 'passed')))
            ->body(new HtmlString($body))->persistent()->send();
    }

    /** @param  array<string, mixed>  $data */
    private function save(?string $engineId, array $data): void
    {
        app(SaveAiEngine::class)->handle($this->admin(), $engineId, $data, (bool) ($data['clear_key'] ?? false));
        Notification::make()->success()->title(__('platform::engines.saved'))->send();
        unset($this->engines);
    }

    private function admin(): PlatformAdmin
    {
        $admin = auth('platform')->user();
        abort_unless($admin instanceof PlatformAdmin && self::canAccess(), 403);

        return $admin;
    }
}
