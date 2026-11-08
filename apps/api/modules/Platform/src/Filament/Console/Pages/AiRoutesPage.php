<?php

namespace Modules\Platform\Filament\Console\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Platform\Actions\Ai\CompareAiChains;
use Modules\Platform\Actions\Ai\SaveAiRoutes;
use Modules\Platform\Actions\Ai\SaveAiTestCase;
use Modules\Platform\Filament\Console\ConsoleView;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\Ai\AiEngine;
use Modules\Platform\Models\Ai\AiRoute;
use Modules\Platform\Models\Ai\AiTestCase;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\SaasPlan;
use Modules\SharedKernel\Ai\Capabilities;
use Modules\SharedKernel\Ai\Capability;

/**
 * Console › AI routing (owners only): one row for each capability a module
 * registered and each kind of work it does (a new one appears here at once,
 * on the server's settings until it has a route), with its default chain of
 * engines, the plans' and academies' own chains, and any share sent to a
 * second chain for comparison. "Edit" sets a scope's chain (an empty chain
 * removes it, and the next scope holds). With ?academy= (from the academy's
 * page) the screen sets that academy's own routes. "Compare" runs saved test
 * inputs (written here, never a learner's data) through two chains and
 * shows the answers, times and costs side by side; nothing reaches an
 * academy.
 */
final class AiRoutesPage extends Page
{
    protected string $view = 'platform::console.ai-routes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 32;

    protected static ?string $slug = 'ai/routes';

    /** The academy whose own routes this screen sets (from its page), or none. */
    #[Url]
    public ?string $academy = null;

    /** @var list<array<string, mixed>> the last comparison's rows, for this screen only */
    public array $comparison = [];

    public string $compared = '';

    /** @var array{0?: string, 1?: string} the capability and kind the open "Edit" is for */
    public array $editing = [];

    public static function canAccess(): bool
    {
        $admin = auth('platform')->user();

        return $admin instanceof PlatformAdmin && $admin->is_owner && $admin->is_active;
    }

    public static function getNavigationLabel(): string
    {
        return __('platform::routes.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::routes.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::routes.subtitle');
    }

    public function mount(): void
    {
        if ($this->academy !== null && (preg_match('/^[0-9a-f-]{36}$/i', $this->academy) !== 1 || ! Academy::query()->whereKey($this->academy)->exists())) {
            $this->academy = null;
        }
    }

    #[Computed]
    public function academyName(): ?string
    {
        $academy = $this->academy !== null ? Academy::query()->find($this->academy) : null;

        return $academy !== null ? ConsoleView::name($academy) : null;
    }

    /** @return list<array<string, mixed>> one row per capability and kind */
    #[Computed]
    public function rows(): array
    {
        $routes = AiRoute::query()->get()->groupBy(fn (AiRoute $r) => $r->capability.'|'.$r->kind);
        $engines = AiEngine::query()->get()->keyBy('id');
        $plans = SaasPlan::query()->get()->mapWithKeys(fn (SaasPlan $p) => [AiRoute::planScope($p->code) => $p->name()]);
        $academies = Academy::query()->get()->mapWithKeys(fn (Academy $a) => [AiRoute::academyScope($a->id) => ConsoleView::name($a)]);

        $rows = [];
        foreach (app(Capabilities::class)->all() as $capability) {
            foreach ($capability->kinds as $kind) {
                $mine = $routes->get($capability->key.'|'.$kind, collect())->keyBy('scope');
                $show = fn (AiRoute $r) => ['chain' => $this->chainShown($r->chain, $engines), 'split' => $r->split_percent,
                    'split_chain' => $r->split_chain ? $this->chainShown($r->split_chain, $engines) : []];
                $scoped = fn (string $prefix, $names) => $mine->filter(fn (AiRoute $r) => str_starts_with($r->scope, $prefix))
                    ->map(fn (AiRoute $r) => ['scope' => $r->scope, 'name' => $names[$r->scope] ?? $r->scope] + $show($r))->values()->all();
                $academy = $this->academy !== null ? $mine->get(AiRoute::academyScope($this->academy)) : null;
                $rows[] = [
                    'key' => $capability->key,
                    'kind' => $kind,
                    'label' => $capability->label(),
                    'credits' => $capability->credits,
                    'tier' => $capability->tier,
                    'default' => ($d = $mine->get(AiRoute::DEFAULT)) !== null ? $show($d) : null,
                    'plans' => $scoped('plan:', $plans),
                    'academies' => $this->academy === null ? $scoped('academy:', $academies) : [],
                    'this_academy' => $academy !== null ? $show($academy) : null,
                ];
            }
        }

        return $rows;
    }

    /** @return list<array{name: string, tier: ?string, off: bool}> */
    private function chainShown(array $chain, $engines): array
    {
        return array_values(array_map(fn (array $s) => [
            'name' => $engines->get($s['engine'] ?? '')?->name ?? '?',
            'tier' => $s['tier'] ?? null,
            'off' => ! ($engines->get($s['engine'] ?? '')?->enabled ?? false),
        ], $chain));
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function cases(): array
    {
        $capabilities = app(Capabilities::class);

        return AiTestCase::query()->orderBy('capability')->orderBy('title')->get()->map(fn (AiTestCase $c) => [
            'id' => $c->id,
            'title' => $c->title,
            'capability' => $capabilities->get($c->capability)?->label() ?? $c->capability,
            'kind' => $c->kind,
        ])->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('compare')->label(__('platform::routes.compare.label'))->icon(Heroicon::OutlinedScale)
                ->modalHeading(__('platform::routes.compare.label'))->modalDescription(__('platform::routes.compare.about'))->modalWidth('4xl')
                ->modalSubmitActionLabel(__('platform::routes.compare.run'))
                ->schema(fn () => $this->compareForm())
                ->fillForm(fn () => ['target' => array_key_first($this->targets())])
                ->action(fn (array $data) => $this->compare($data)),
            Action::make('newCase')->label(__('platform::routes.cases.new'))->icon(Heroicon::Plus)->color('gray')
                ->modalHeading(__('platform::routes.cases.new'))->modalWidth('3xl')
                ->schema(fn () => $this->caseForm())
                ->fillForm(fn () => ['target' => array_key_first($this->targets()), 'language' => 'ara'])
                ->action(fn (array $data) => $this->saveCase($data)),
        ];
    }

    public function editAction(): Action
    {
        return Action::make('edit')->label(__('platform::routes.edit'))->icon(Heroicon::OutlinedPencilSquare)->color('gray')->size('sm')
            ->modalHeading(fn (array $arguments) => __('platform::routes.edit').' · '.app(Capabilities::class)->require($arguments['capability'])->label()
                .' ('.__('platform::routes.kinds.'.$arguments['kind']).')')
            ->modalWidth('3xl')
            ->schema(fn (array $arguments) => $this->routeForm($arguments['kind']))
            ->fillForm(function (array $arguments) {
                $this->editing = [$arguments['capability'], $arguments['kind']];
                $scope = $arguments['scope'] ?? ($this->academy !== null ? AiRoute::academyScope($this->academy) : AiRoute::DEFAULT);

                return ['scope' => $scope] + $this->routeState($arguments['capability'], $arguments['kind'], $scope);
            })
            ->action(function (array $data, array $arguments) {
                $split = filled($data['split_percent'] ?? null) ? (int) $data['split_percent'] : null;
                app(SaveAiRoutes::class)->handle($this->admin(), $arguments['capability'], $arguments['kind'], (string) $data['scope'],
                    array_values($data['chain'] ?? []), $split, array_values($data['split_chain'] ?? []));
                Notification::make()->success()->title(__('platform::routes.saved'))->send();
                unset($this->rows);
            });
    }

    public function removeCaseAction(): Action
    {
        return Action::make('removeCase')->label(__('platform::routes.cases.remove'))->icon(Heroicon::OutlinedTrash)->color('danger')->size('xs')->link()
            ->requiresConfirmation()
            ->action(function (array $arguments) {
                app(SaveAiTestCase::class)->delete($this->admin(), $arguments['case']);
                unset($this->cases);
            });
    }

    /** @return array<string, mixed> the form's state for a scope's route as saved (empty: none yet) */
    private function routeState(string $capability, string $kind, string $scope): array
    {
        $route = AiRoute::query()->where(['capability' => $capability, 'kind' => $kind, 'scope' => $scope])->first();

        return [
            'chain' => $route?->chain ?? [],
            'split_percent' => $route?->split_percent,
            'split_chain' => $route?->split_chain ?? [],
        ];
    }

    /** @return list<mixed> */
    private function routeForm(string $kind): array
    {
        $r = 'platform::routes.form.';

        return [
            Select::make('scope')->label(__($r.'scope'))->options($this->scopes())->required()->native(false)->searchable()->live()
                ->helperText(__($r.'scope_hint'))
                ->afterStateUpdated(function (?string $state, Set $set) {
                    [$capability, $kind] = $this->editing + ['', 'text'];
                    foreach ($this->routeState((string) $capability, (string) $kind, (string) $state) as $field => $value) {
                        $set($field, $value);
                    }
                }),
            Repeater::make('chain')->label(__($r.'chain'))->helperText(__($r.'chain_hint'))
                ->schema($this->stepFields($kind))->columns(2)->reorderable()->maxItems(SaveAiRoutes::MAX_STEPS)->defaultItems(0)
                ->addActionLabel(__($r.'add_step')),
            Section::make(__($r.'split'))->description(__($r.'split_hint'))->compact()->collapsible()->collapsed(fn (Get $get) => blank($get('split_percent')))->schema([
                TextInput::make('split_percent')->label(__($r.'split_percent'))->numeric()->integer()->minValue(1)->maxValue(99)->suffix('%'),
                Repeater::make('split_chain')->label(__($r.'split_chain'))->schema($this->stepFields($kind))->columns(2)->reorderable()
                    ->maxItems(SaveAiRoutes::MAX_STEPS)->defaultItems(0)->addActionLabel(__($r.'add_step')),
            ]),
        ];
    }

    /** @return list<mixed> */
    private function stepFields(string $kind): array
    {
        $r = 'platform::routes.form.';

        return [
            Select::make('engine')->label(__($r.'engine'))->options(fn () => $this->engineOptions($kind))->required()->native(false),
            Select::make('tier')->label(__($r.'tier'))->options(['fast' => __($r.'tiers.fast'), 'best' => __($r.'tiers.best')])
                ->placeholder(__($r.'tiers.prompt'))->native(false)->visible($kind === 'text'),
        ];
    }

    /** @return array<string, string> the engines that do this kind of work (a switched-off one says so) */
    private function engineOptions(string $kind): array
    {
        return AiEngine::query()->orderBy('name')->get()->filter(fn (AiEngine $e) => $e->kindOf()->serves($kind))
            ->mapWithKeys(fn (AiEngine $e) => [$e->id => $e->name.($e->enabled ? '' : ' ('.__('platform::engines.card.disabled').')')])->all();
    }

    /** @return array<string, string> default, each plan, each academy (this academy only, when the screen is for one) */
    private function scopes(): array
    {
        $r = 'platform::routes.scopes.';
        if ($this->academy !== null) {
            return [AiRoute::academyScope($this->academy) => __($r.'academy', ['name' => (string) $this->academyName])];
        }
        $out = [AiRoute::DEFAULT => __($r.'default')];
        foreach (SaasPlan::query()->whereNull('archived_at')->orderBy('sort')->get() as $plan) {
            $out[AiRoute::planScope($plan->code)] = __($r.'plan', ['name' => $plan->name()]);
        }
        foreach (Academy::query()->orderBy('name_en')->get() as $academy) {
            $out[AiRoute::academyScope($academy->id)] = __($r.'academy', ['name' => ConsoleView::name($academy)]);
        }

        return $out;
    }

    /** @return array<string, string> "key|kind" => the capability and its kind of work */
    private function targets(): array
    {
        $out = [];
        foreach (app(Capabilities::class)->all() as $c) {
            foreach ($c->kinds as $kind) {
                $out[$c->key.'|'.$kind] = $c->label().' · '.__('platform::routes.kinds.'.$kind);
            }
        }

        return $out;
    }

    /** @return list<mixed> */
    private function compareForm(): array
    {
        $r = 'platform::routes.compare.';
        $kind = fn (Get $get) => explode('|', (string) $get('target'))[1] ?? 'text';

        return [
            Select::make('target')->label(__($r.'target'))->options($this->targets())->required()->native(false)->live(),
            Grid::make(2)->schema([
                Repeater::make('a')->label(__($r.'a'))->schema(fn (Get $get) => $this->stepFields($kind($get)))->minItems(1)->maxItems(SaveAiRoutes::MAX_STEPS)
                    ->defaultItems(1)->addActionLabel(__('platform::routes.form.add_step')),
                Repeater::make('b')->label(__($r.'b'))->schema(fn (Get $get) => $this->stepFields($kind($get)))->minItems(1)->maxItems(SaveAiRoutes::MAX_STEPS)
                    ->defaultItems(1)->addActionLabel(__('platform::routes.form.add_step')),
            ]),
            CheckboxList::make('cases')->label(__($r.'cases'))->helperText(__($r.'cases_hint', ['max' => CompareAiChains::MAX_CASES]))->required()
                ->options(function (Get $get) {
                    [$key, $kind] = array_pad(explode('|', (string) $get('target')), 2, 'text');

                    return AiTestCase::query()->where('capability', $key)->where('kind', $kind)->orderBy('title')->pluck('title', 'id')->all();
                }),
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function compare(array $data): void
    {
        [$key, $kind] = array_pad(explode('|', (string) $data['target']), 2, 'text');
        $this->comparison = app(CompareAiChains::class)->handle($this->admin(), $key, $kind, array_values($data['a'] ?? []), array_values($data['b'] ?? []), array_values($data['cases'] ?? []));
        $this->compared = $this->targets()[$data['target']] ?? $key;
    }

    /** @return list<mixed> */
    private function caseForm(): array
    {
        $f = 'platform::routes.cases.fields.';
        $speech = fn (Get $get) => str_ends_with((string) $get('target'), '|speech');

        return [
            Callout::make(__('platform::routes.cases.no_learner_data'))->warning()->columnSpanFull(),
            Select::make('target')->label(__($f.'capability'))->options($this->targets())->required()->native(false)->live(),
            TextInput::make('title')->label(__($f.'title'))->required()->maxLength(120),
            Textarea::make('system')->label(__($f.'system'))->rows(3)->maxLength(20000)->visible(fn (Get $get) => ! $speech($get)),
            Textarea::make('prompt')->label(__($f.'prompt'))->rows(5)->maxLength(20000)->required(fn (Get $get) => ! $speech($get))->visible(fn (Get $get) => ! $speech($get)),
            Textarea::make('schema')->label(__($f.'schema'))->helperText(__('platform::routes.cases.schema_hint'))->rows(4)->maxLength(20000)
                ->visible(fn (Get $get) => ! $speech($get)),
            FileUpload::make('audio')->label(__($f.'audio'))->storeFiles(false)->acceptedFileTypes(SaveAiTestCase::AUDIO)->maxSize(SaveAiTestCase::MAX_KB)
                ->required(fn (Get $get) => $speech($get))->visible(fn (Get $get) => $speech($get)),
            Select::make('language')->label(__($f.'language'))->options(['ara' => __('platform::routes.cases.languages.ara'), 'eng' => __('platform::routes.cases.languages.eng')])
                ->native(false)->visible(fn (Get $get) => $speech($get)),
            Textarea::make('notes')->label(__($f.'notes'))->rows(2)->maxLength(1000),
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function saveCase(array $data): void
    {
        [$key, $kind] = array_pad(explode('|', (string) $data['target']), 2, 'text');
        $audio = $data['audio'] ?? null;
        app(SaveAiTestCase::class)->handle($this->admin(), null, [
            'capability' => $key, 'kind' => $kind, 'title' => $data['title'] ?? '', 'system' => $data['system'] ?? null,
            'prompt' => $data['prompt'] ?? null, 'schema' => $data['schema'] ?? null, 'language' => $data['language'] ?? 'ara', 'notes' => $data['notes'] ?? null,
        ], is_array($audio) ? (reset($audio) ?: null) : $audio);
        Notification::make()->success()->title(__('platform::routes.cases.saved'))->send();
        unset($this->cases);
    }

    private function admin(): PlatformAdmin
    {
        $admin = auth('platform')->user();
        abort_unless($admin instanceof PlatformAdmin && self::canAccess(), 403);

        return $admin;
    }

    /** For the view: a capability's description. */
    public static function about(string $key): string
    {
        return app(Capabilities::class)->get($key)?->description() ?? '';
    }

    /** @return array<string, Capability> */
    public static function capabilities(): array
    {
        return app(Capabilities::class)->all();
    }
}
