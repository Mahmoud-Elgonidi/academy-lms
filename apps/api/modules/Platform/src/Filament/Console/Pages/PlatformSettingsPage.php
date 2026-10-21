<?php

namespace Modules\Platform\Filament\Console\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\Platform\Actions\SavePlatformSettings;
use Modules\Platform\Ai\AiProvider;
use Modules\Platform\Ai\ModelCatalog;
use Modules\Platform\Ai\ModelList;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Health\OutsideServices;
use Modules\Platform\Health\SystemHealth;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Settings\CheckLine;
use Modules\Platform\Settings\CheckResult;
use Modules\Platform\Settings\LastChecks;
use Modules\Platform\Settings\PlatformSettings;
use Modules\Platform\Settings\SettingCatalog;
use Modules\Platform\Settings\SettingChecks;
use Modules\Platform\Settings\SettingDefinition;
use Modules\Platform\Settings\SettingValues;
use Modules\SharedKernel\Text\QuranFetchFailed;
use Modules\SharedKernel\Text\QuranSource;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * Platform settings (owners of the platform team only): every outside
 * service's keys and addresses in one place instead of the server's .env,
 * a section each with a "Try it". A saved key or file is never shown again:
 * blank keeps it, "Remove" takes it away. Each setting says where its value
 * comes from now: this screen (who, when), the server's .env, or nowhere.
 * The AI section asks first who writes the words, then shows that provider's
 * key and its models as a list from the provider itself (ModelCatalog,
 * loaded after the page opens so it never waits on the network), with
 * "another name…" last. A key typed and not saved yet stays in the page's
 * state until it is saved, as after a failed save; it is never logged.
 * "Try it" tries what is typed in its section before it is saved (else what
 * is in force) and its result stays under the section's title with how each
 * service answered lately; the services that need someone head the page.
 * Below, the Quran's text on the server, and fetching it from Tanzil.
 *
 * @property-read Schema $form
 */
final class PlatformSettingsPage extends Page
{
    protected string $view = 'platform::console.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'settings';

    public ?array $data = [];

    /** @var array<string, ?array{heading: string, body: HtmlString, status: string}> each section's found(), read once a request */
    private array $found = [];

    public static function canAccess(): bool
    {
        $admin = auth('platform')->user();

        return $admin instanceof PlatformAdmin && $admin->is_owner && $admin->is_active;
    }

    public static function getNavigationLabel(): string
    {
        return __('platform::settings.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::settings.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::settings.subtitle');
    }

    public function mount(): void
    {
        $saved = app(PlatformSettings::class)->values();
        $this->form->fill(collect(SettingCatalog::all())
            ->reject(fn (SettingDefinition $s) => $s->secret())
            ->mapWithKeys(fn (SettingDefinition $s, string $key) => [$key => $saved[$key] ?? null])
            ->all());
    }

    /** @return array<string, array{by: ?string, at: string}> who saved each value here, and when */
    #[Computed]
    public function saved(): array
    {
        $saved = app(PlatformSettings::class)->saved();
        $names = PlatformAdmin::query()->whereIn('id', array_filter(array_column($saved, 'by')))->pluck('name', 'id')->all();
        $tz = app(ViewerTimezone::class)->effective();

        return array_map(fn (array $row) => [
            'by' => $names[$row['by']] ?? null,
            'at' => CarbonImmutable::parse($row['at'])->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M Y، H:i'),
        ], $saved);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components(array_map(fn (string $group) => Section::make(__("platform::settings.groups.{$group}.title"))
            ->id('ps-'.$group)
            ->description(__("platform::settings.groups.{$group}.about"))
            ->collapsible()
            ->afterHeader(in_array($group, SettingChecks::GROUPS, true) ? [
                Action::make('check_'.$group)->label(__('platform::settings.checks.try'))->icon('heroicon-m-signal')->color('gray')->size('sm')
                    ->action(fn () => $this->check($group)),
            ] : [])
            ->columns(2)
            ->schema([...$this->notes($group), ...collect(SettingCatalog::inGroup($group))->flatMap(fn (SettingDefinition $s) => $this->fields($s))->all()]), SettingCatalog::GROUPS));
    }

    /** @return list<Callout> what a section says before its fields */
    private function notes(string $group): array
    {
        $found = in_array($group, SettingChecks::GROUPS, true) ? [
            Callout::make(fn () => $this->found($group)['heading'])
                ->description(fn () => $this->found($group)['body'])
                ->status(fn () => $this->found($group)['status'])
                ->visible(fn () => $this->found($group) !== null)
                ->columnSpanFull(),
        ] : [];
        if ($group !== 'ai') {
            return $found;
        }

        return [...$found,
            Callout::make(__('platform::settings.ai_notes.gemini_paid'))->warning()->columnSpanFull()
                ->visible(fn (Get $get) => $this->driver($get) === 'gemini'),
            Callout::make(fn (Get $get) => __('platform::settings.ai_notes.'.$this->driver($get)))->color('gray')->columnSpanFull()
                ->visible(fn (Get $get) => in_array($this->driver($get), ['off', 'demo'], true)),
        ];
    }

    /** Who writes the words: the choice on the screen, else the one in force. */
    private function driver(Get $get): string
    {
        return (string) ($get('ai_driver') ?: config('platform.ai.driver'));
    }

    /** @return list<Field|Group> */
    private function fields(SettingDefinition $setting): array
    {
        $key = $setting->key;
        $shown = fn () => $this->serverShown($setting);
        $field = match ($setting->type) {
            'secret' => TextInput::make($key)->password()->autocomplete('new-password')
                ->placeholder(fn () => isset($this->saved[$key]) ? __('platform::settings.secret_kept') : null),
            'model' => Select::make($key)->searchable()->live()
                ->options(fn (Get $get) => $this->modelOptions($setting, $get))
                ->placeholder($shown)
                ->hintAction(Action::make('reload_'.$key)->icon('heroicon-m-arrow-path')->label(__('platform::settings.models.reload'))
                    ->action(fn () => $this->reloadModels($setting))),
            'json_file' => FileUpload::make($key)->storeFiles(false)->acceptedFileTypes(['application/json', 'text/plain'])
                ->maxSize((int) ceil(SavePlatformSettings::JSON_MAX / 1024)),
            'select' => Select::make($key)->options(collect($setting->options)->mapWithKeys(fn (string $o) => [$o => __("platform::settings.options.{$key}.{$o}")])->all())
                ->placeholder($shown),
            'toggle' => Select::make($key)->options(['1' => __('platform::settings.on'), '0' => __('platform::settings.off')])->placeholder($shown),
            'int' => TextInput::make($key)->integer()->placeholder($shown),
            default => TextInput::make($key)->placeholder($shown),
        };
        $help = e(__("platform::settings.keys.{$key}.help")).($setting->env ? ' <code dir="ltr" style="font-size:.75rem">'.e($setting->env).'</code>' : '');
        $field->label(__("platform::settings.keys.{$key}.label"))
            ->helperText($setting->type === 'model'
                ? fn (Get $get) => new HtmlString($help.'<br>'.e($this->modelNote($setting, $get)))
                : new HtmlString($help))
            ->hint(fn () => $this->source($setting)[0])->hintColor(fn () => $this->source($setting)[1])
            ->hintIcon(fn () => $this->source($setting)[2])->hintIconTooltip(fn () => $this->source($setting)[3]);

        // The provider's own settings show only while it is chosen; the list follows its key or address as typed.
        $mine = fn (Get $get) => $setting->driver === null || $this->driver($get) === $setting->driver;
        if ($key === 'ai_driver') {
            $field->live()->afterStateUpdated(fn () => $this->warmModels());
        } elseif ($setting->group === 'ai' && ($setting->type === 'secret' || $key === 'ollama_url') && $key !== 'elevenlabs_key') {
            $field->live(onBlur: true)->afterStateUpdated(fn () => $this->warmModels());
        }

        if ($setting->type === 'model') {
            return [Group::make([
                $field,
                TextInput::make('custom.'.$key)->label(__('platform::settings.models.custom_label'))
                    ->visible(fn (Get $get) => $get($key) === ModelCatalog::CUSTOM)
                    ->required(fn (Get $get) => $get($key) === ModelCatalog::CUSTOM),
            ])->visible($mine)];
        }
        if (! $setting->secret()) {
            return [$setting->driver === null ? $field : $field->visible($mine)];
        }

        // A key and its "Remove" share one place on the grid.
        return [Group::make([
            $field,
            Checkbox::make('clear.'.$key)->label(__('platform::settings.clear', ['name' => __("platform::settings.keys.{$key}.label")]))
                ->visible(fn () => isset($this->saved[$key])),
        ])->visible($mine)];
    }

    /**
     * A model setting's choices: its provider's list (as kept: the page never
     * waits on the network), the model in use if the list lacks it, and
     * "another name…".
     *
     * @return array<string, string>
     */
    private function modelOptions(SettingDefinition $setting, Get $get): array
    {
        $list = $this->modelList($setting, $get);
        // The provider's own list once it can be had; until then the models known here (Claude's).
        $options = $list !== null && $list->options !== [] ? $list->options : ModelCatalog::known(AiProvider::from((string) $setting->models));
        $current = (string) ($get($setting->key) ?: app(PlatformSettings::class)->serverValue($setting));
        if ($current !== '' && $current !== ModelCatalog::CUSTOM && ! isset($options[$current])) {
            $options = [$current => __('platform::settings.models.current', ['model' => $current])] + $options;
        }

        return $options + [ModelCatalog::CUSTOM => __('platform::settings.models.custom')];
    }

    /** Under a model setting: how many models the list has, that it is loading, or why it could not be had (and that the known ones show meanwhile). */
    private function modelNote(SettingDefinition $setting, Get $get): string
    {
        $list = $this->modelList($setting, $get);
        $provider = AiProvider::from((string) $setting->models);
        $known = ModelCatalog::known($provider) !== [];
        if ($list === null) {
            return __($known ? 'platform::settings.models.loading_known' : 'platform::settings.models.loading', ['provider' => $provider->label()]);
        }
        if ($list->problem !== null && $list->code === 'missing') {
            return $known
                ? __('platform::settings.models.known', ['provider' => $provider->label()])
                : __('platform::settings.models.failed', ['why' => __('platform::settings.models.no_key')]);
        }
        if ($list->problem !== null) {
            return __('platform::settings.models.failed', ['why' => __('platform::services.short.'.$list->problem->value)])
                .($known ? ' '.__('platform::settings.models.known_meanwhile', ['provider' => $provider->label()]) : '');
        }

        return trans_choice('platform::settings.models.count', count($list->options), ['count' => count($list->options), 'provider' => $provider->label()]);
    }

    private function modelList(SettingDefinition $setting, Get $get): ?ModelList
    {
        $provider = AiProvider::from((string) $setting->models);
        [$key, $base] = $this->typedCredentials($provider, fn (string $k) => $get($k));

        return app(ModelCatalog::class)->cached($provider, $key, $base);
    }

    /** @return array{0: ?string, 1: ?string} the key and address typed for a provider (null: the ones in force) */
    private function typedCredentials(AiProvider $provider, callable $typed): array
    {
        return match ($provider) {
            AiProvider::Claude => [$typed('anthropic_key'), null],
            AiProvider::OpenAi => [$typed('openai_key'), null],
            AiProvider::Gemini => [$typed('gemini_key'), null],
            AiProvider::Ollama => [$typed('ollama_key'), $typed('ollama_url')],
        };
    }

    /** Loads the lists the AI section shows (the chosen provider's, and Claude's for the games designer), after the page opens. */
    public function warmModels(): void
    {
        abort_unless(self::canAccess(), 403);
        $typed = fn (string $k) => $this->data[$k] ?? null;
        $driver = (string) (($this->data['ai_driver'] ?? null) ?: config('platform.ai.driver'));
        $providers = collect([AiProvider::tryFrom($driver), AiProvider::Claude])->filter()->unique(fn (AiProvider $p) => $p->value)->all();
        foreach ($providers as $provider) {
            [$key, $base] = $this->typedCredentials($provider, $typed);
            app(ModelCatalog::class)->list($provider, $key, $base);
        }
    }

    /** Asks the provider for its list again (a few times a minute at most). */
    public function reloadModels(SettingDefinition $setting): void
    {
        abort_unless(self::canAccess(), 403);
        $limit = 'settings-models:'.auth('platform')->id();
        if (RateLimiter::tooManyAttempts($limit, 6)) {
            Notification::make()->warning()->title(__('platform::settings.models.slow_down'))->send();

            return;
        }
        RateLimiter::hit($limit, 60);
        $provider = AiProvider::from((string) $setting->models);
        [$key, $base] = $this->typedCredentials($provider, fn (string $k) => $this->data[$k] ?? null);
        app(ModelCatalog::class)->forget($provider, $key, $base);
        app(ModelCatalog::class)->list($provider, $key, $base);
    }

    /** What the server's own value is, shown faintly in an empty field (never a key). */
    private function serverShown(SettingDefinition $setting): ?string
    {
        $value = app(PlatformSettings::class)->serverValue($setting);
        $value = is_array($value) ? implode(', ', $value) : $value;
        if (blank($value)) {
            return null;
        }
        if ($setting->type === 'toggle') {
            return __('platform::settings.from_server', ['value' => __($value ? 'platform::settings.on' : 'platform::settings.off')]);
        }
        if ($setting->type === 'select') {
            $option = $setting->fromConfig($value);
            $value = trans()->has("platform::settings.options.{$setting->key}.{$option}") ? __("platform::settings.options.{$setting->key}.{$option}") : $value;
        }

        return __('platform::settings.from_server', ['value' => (string) $value]);
    }

    /** @return array{0: string, 1: string, 2: string, 3: ?string} where the value in force comes from: the hint, its colour, icon and the icon's tooltip */
    private function source(SettingDefinition $setting): array
    {
        $saved = $this->saved[$setting->key] ?? null;
        if ($saved !== null) {
            return [__('platform::settings.source.screen'), 'success', 'heroicon-m-check-circle', __('platform::settings.source.saved_by', ['who' => $saved['by'] ?? '—', 'when' => $saved['at']])];
        }
        $server = app(PlatformSettings::class)->serverValue($setting);
        if ($setting->type === 'json_file' && blank($server)) {
            $server = config('communication.push.fcm.credentials');
        }

        return filled($server)
            ? [__('platform::settings.source.server'), 'gray', 'heroicon-m-server', null]
            : [__('platform::settings.source.none'), 'warning', 'heroicon-m-exclamation-triangle', null];
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        $state = $this->form->getState();
        $values = [];
        $clear = array_keys(array_filter((array) ($state['clear'] ?? [])));
        foreach (SettingCatalog::all() as $key => $setting) {
            // A setting not on the screen now (another provider's) is left as it is.
            if (! array_key_exists($key, $state)) {
                continue;
            }
            if ($setting->type === 'model') {
                $values[$key] = $state[$key] === ModelCatalog::CUSTOM ? ($state['custom'][$key] ?? null) : $state[$key];

                continue;
            }
            if ($setting->type !== 'json_file') {
                $values[$key] = $state[$key] ?? null;

                continue;
            }
            // Read once and never kept on disk: the file holds the account's private key.
            $file = collect(is_array($state[$key] ?? null) ? $state[$key] : [$state[$key] ?? null])->first(fn ($f) => $f instanceof TemporaryUploadedFile);
            if ($file !== null) {
                $values[$key] = (string) $file->get();
                $file->delete();
            }
        }

        try {
            $changed = app(SavePlatformSettings::class)->handle(auth('platform')->user(), $values, $clear);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn (array $messages, string $key) => ['data.'.Str::before($key, '.') => $messages])->all());
        }

        unset($this->saved);
        $this->mount();
        Notification::make()->success()->title($changed === [] ? __('platform::settings.nothing_changed') : trans_choice('platform::settings.saved', count($changed), ['count' => count($changed)]))->send();
    }

    /** @return array{available: bool, where: ?string, notice: ?string, verses: ?int, text_sha256: ?string, data_sha256: ?string, fetched_at: ?string} */
    #[Computed]
    public function quran(): array
    {
        $status = app(QuranSource::class)->status();
        if ($status['fetched_at'] !== null) {
            $status['fetched_at'] = CarbonImmutable::parse($status['fetched_at'])->setTimezone(app(ViewerTimezone::class)->effective())
                ->locale(app()->getLocale())->translatedFormat('j M Y، H:i');
        }

        return $status;
    }

    /** Fetches Tanzil's files to the server again (checked whole before they replace the copy in use). */
    public function fetchQuranAction(): Action
    {
        return Action::make('fetchQuran')->label(fn () => __($this->quran['available'] ? 'platform::settings.quran.fetch_again' : 'platform::settings.quran.fetch'))
            ->icon('heroicon-m-arrow-down-tray')->color(fn () => $this->quran['available'] ? 'gray' : 'primary')
            ->requiresConfirmation()->modalDescription(__('platform::settings.quran.confirm'))
            ->action(function () {
                abort_unless(self::canAccess(), 403);
                try {
                    $status = app(QuranSource::class)->fetch();
                } catch (QuranFetchFailed $e) {
                    Notification::make()->danger()->title(__('platform::settings.quran.failed'))->body($e->getMessage())->persistent()->send();

                    return;
                }
                app(PlatformAudit::class)->record(null, 'platform.quran_fetched', 'platform_settings', null, ['verses' => $status['verses']]);
                unset($this->quran);
                Notification::make()->success()->title(__('platform::settings.quran.fetched'))->send();
            });
    }

    public function check(string $group): void
    {
        abort_unless(self::canAccess() && in_array($group, SettingChecks::GROUPS, true), 403);
        $admin = auth('platform')->user();
        $limit = 'settings-check:'.$admin->getKey().':'.$group;
        if (RateLimiter::tooManyAttempts($limit, 6)) {
            Notification::make()->warning()->title(__('platform::settings.checks.slow_down'))->send();

            return;
        }
        RateLimiter::hit($limit, 60);

        [$values, $clear] = $this->typed($group);
        $checks = app(SettingChecks::class);
        try {
            $result = $values === [] && $clear === []
                ? $checks->run($group, (string) $admin->email)
                // What is typed is tried as it is, and never written down as the saved settings' health.
                : new CheckResult(app(PlatformSettings::class)->trying($values, $clear, fn () => $checks->run($group, (string) $admin->email, record: false))->lines, typed: true);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn (array $messages, string $key) => ['data.'.Str::before($key, '.') => $messages])->all());
        }

        LastChecks::put($group, $result, (string) $admin->name);
        $this->found = [];
        unset($this->trouble);
        $note = Notification::make()->title(__("platform::settings.groups.{$group}.title"))->body($result->says());
        match ($result->level()) {
            'fail' => $note->danger(),
            'warn' => $note->warning(),
            default => $note->success(),
        };
        $note->send();
    }

    /**
     * What is typed in a section and differs from what is saved: keys typed,
     * keys ticked to remove, a file chosen, a model named.
     *
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function typed(string $group): array
    {
        $saved = app(PlatformSettings::class)->values();
        $values = [];
        $clear = [];
        foreach (SettingCatalog::inGroup($group) as $setting) {
            $key = $setting->key;
            $typed = $this->data[$key] ?? null;
            if ($setting->secret() && ! empty($this->data['clear'][$key])) {
                $clear[] = $key;

                continue;
            }
            if ($setting->type === 'json_file') {
                $file = collect(is_array($typed) ? $typed : [$typed])->first(fn ($f) => $f instanceof TemporaryUploadedFile);
                if ($file !== null) {
                    $values[$key] = (string) $file->get();
                }

                continue;
            }
            if ($setting->type === 'model' && $typed === ModelCatalog::CUSTOM) {
                $typed = $this->data['custom'][$key] ?? null;
            }
            $value = SettingValues::normalize($setting, $typed);
            if ($setting->secret() ? $value !== '' : $value !== SettingValues::normalize($setting, $saved[$key] ?? null)) {
                $values[$key] = $value;
            }
        }

        return [$values, $clear];
    }

    /**
     * Under a section's title: what its last try found (when, who, whether
     * with values typed), and how each of its services answered lately.
     *
     * @return ?array{heading: string, body: HtmlString, status: string}
     */
    private function found(string $group): ?array
    {
        return array_key_exists($group, $this->found) ? $this->found[$group] : $this->found[$group] = $this->readFound($group);
    }

    /** @return ?array{heading: string, body: HtmlString, status: string} */
    private function readFound(string $group): ?array
    {
        $last = LastChecks::get($group);
        $services = array_values(array_filter(OutsideServices::configured(), fn (string $s) => OutsideServices::group($s) === $group));
        if ($last === null && $services === []) {
            return null;
        }

        $body = '';
        $level = 'ok';
        if ($last !== null) {
            $result = $last['result'];
            $level = $result->level();
            // Inside the callout's paragraph: lines as blocks of spans.
            foreach ($result->lines as $line) {
                $body .= '<span style="display:block;margin-block-end:.3rem">'.$this->mark($line->level).' '.$line->html().($line->todoHtml() !== null ? ' <span style="color:var(--gray-500)">'.$line->todoHtml().'</span>' : '').'</span>';
            }
            $when = CarbonImmutable::createFromTimestamp($last['at'])->locale(app()->getLocale())->diffForHumans(['options' => CarbonInterface::JUST_NOW]);
            $body .= '<span style="display:block;margin-block-start:.4rem;font-size:.8rem;color:var(--gray-500)">'
                .e($last['by'] !== null ? __('platform::settings.checks.last', ['when' => $when, 'who' => $last['by']]) : __('platform::settings.checks.last_daily', ['when' => $when]))
                .($result->typed ? ' · '.e(__('platform::settings.checks.typed')) : '').'</span>';
        }
        if ($services !== []) {
            $health = app(SystemHealth::class);
            $body .= '<span style="display:block;margin-block-start:.6rem;font-size:.8rem;font-weight:600">'.e(__('platform::settings.checks.in_use')).'</span>';
            foreach ($services as $service) {
                $now = $health->service($service);
                $body .= '<span style="display:block;font-size:.85rem">'.$this->mark($now['status']).' <bdi>'.e(__('platform::services.names.'.$service)).'</bdi>: '.e($now['value']).'</span>';
                $level = CheckLine::LEVELS[$now['status']] > CheckLine::LEVELS[$level] ? $now['status'] : $level;
            }
        }
        $heading = $last !== null
            ? __('platform::settings.checks.level.'.$last['result']->level())
            : __('platform::settings.checks.in_use');

        return ['heading' => $heading, 'body' => new HtmlString($body), 'status' => match ($level) {
            'fail' => 'danger',
            'warn' => 'warning',
            default => $last !== null ? 'success' : 'info',
        }];
    }

    private function mark(string $level): string
    {
        [$sign, $colour] = match ($level) {
            'fail' => ['✕', 'var(--danger-600)'],
            'warn' => ['!', 'var(--warning-600)'],
            'ok' => ['✓', 'var(--success-600)'],
            default => ['•', 'var(--gray-500)'],
        };

        return '<span aria-hidden="true" style="display:inline-block;min-inline-size:1rem;font-weight:700;color:'.$colour.'">'.$sign.'</span>';
    }

    /**
     * The outside services that need someone (SystemHealth's warn or fail),
     * for the banner at the top of the page.
     *
     * @return list<array{service: string, name: string, status: string, value: string, hint: ?string, group: string}>
     */
    #[Computed]
    public function trouble(): array
    {
        $health = app(SystemHealth::class);
        $trouble = [];
        foreach (OutsideServices::configured() as $service) {
            $now = $health->service($service);
            if ($now['status'] !== 'ok') {
                $trouble[] = ['service' => $service, 'name' => __('platform::services.names.'.$service), 'group' => OutsideServices::group($service)] + $now;
            }
        }
        usort($trouble, fn (array $a, array $b) => CheckLine::LEVELS[$b['status']] <=> CheckLine::LEVELS[$a['status']]);

        return $trouble;
    }
}
