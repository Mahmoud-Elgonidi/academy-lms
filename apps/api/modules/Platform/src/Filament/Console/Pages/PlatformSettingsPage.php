<?php

namespace Modules\Platform\Filament\Console\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\Platform\Actions\SavePlatformSettings;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Settings\PlatformSettings;
use Modules\Platform\Settings\SettingCatalog;
use Modules\Platform\Settings\SettingChecks;
use Modules\Platform\Settings\SettingDefinition;
use Modules\SharedKernel\Text\QuranFetchFailed;
use Modules\SharedKernel\Text\QuranSource;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * Platform settings (owners of the platform team only): every outside
 * service's keys and addresses in one place instead of the server's .env,
 * a section each with a "Try it". A saved key or file is never shown again:
 * blank keeps it, "Remove" takes it away. Each setting says where its value
 * comes from now: this screen (who, when), the server's .env, or nowhere.
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
            ->description(__("platform::settings.groups.{$group}.about"))
            ->collapsible()
            ->afterHeader(in_array($group, SettingChecks::GROUPS, true) ? [
                Action::make('check_'.$group)->label(__('platform::settings.checks.try'))->icon('heroicon-m-signal')->color('gray')->size('sm')
                    ->action(fn () => $this->check($group)),
            ] : [])
            ->columns(2)
            ->schema(collect(SettingCatalog::inGroup($group))->flatMap(fn (SettingDefinition $s) => $this->fields($s))->all()), SettingCatalog::GROUPS));
    }

    /** @return list<Field> */
    private function fields(SettingDefinition $setting): array
    {
        $key = $setting->key;
        $shown = fn () => $this->serverShown($setting);
        $field = match ($setting->type) {
            'secret' => TextInput::make($key)->password()->autocomplete('new-password')
                ->placeholder(fn () => isset($this->saved[$key]) ? __('platform::settings.secret_kept') : null),
            'json_file' => FileUpload::make($key)->storeFiles(false)->acceptedFileTypes(['application/json', 'text/plain'])
                ->maxSize((int) ceil(SavePlatformSettings::JSON_MAX / 1024)),
            'select' => Select::make($key)->options(collect($setting->options)->mapWithKeys(fn (string $o) => [$o => __("platform::settings.options.{$key}.{$o}")])->all())
                ->placeholder($shown),
            'toggle' => Select::make($key)->options(['1' => __('platform::settings.on'), '0' => __('platform::settings.off')])->placeholder($shown),
            'int' => TextInput::make($key)->integer()->placeholder($shown),
            default => TextInput::make($key)->placeholder($shown),
        };
        $field->label(__("platform::settings.keys.{$key}.label"))
            ->helperText(new HtmlString(e(__("platform::settings.keys.{$key}.help")).($setting->env ? ' <code dir="ltr" style="font-size:.75rem">'.e($setting->env).'</code>' : '')))
            ->hint(fn () => $this->source($setting)[0])->hintColor(fn () => $this->source($setting)[1])->hintIcon(fn () => $this->source($setting)[2]);

        $fields = [$field];
        if ($setting->secret()) {
            $fields[] = Checkbox::make('clear.'.$key)->label(__('platform::settings.clear', ['name' => __("platform::settings.keys.{$key}.label")]))
                ->visible(fn () => isset($this->saved[$key]));
        }

        return $fields;
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
            $value = in_array($option, $setting->options, true) ? __("platform::settings.options.{$setting->key}.{$option}") : $value;
        }

        return __('platform::settings.from_server', ['value' => (string) $value]);
    }

    /** @return array{0: string, 1: string, 2: string} where the value in force comes from: the hint, its colour and icon */
    private function source(SettingDefinition $setting): array
    {
        $saved = $this->saved[$setting->key] ?? null;
        if ($saved !== null) {
            return [__('platform::settings.source.screen', ['who' => $saved['by'] ?? '—', 'when' => $saved['at']]), 'success', 'heroicon-m-check-circle'];
        }
        $server = app(PlatformSettings::class)->serverValue($setting);
        if ($setting->type === 'json_file' && blank($server)) {
            $server = config('communication.push.fcm.credentials');
        }

        return filled($server)
            ? [__('platform::settings.source.server'), 'gray', 'heroicon-m-server']
            : [__('platform::settings.source.none'), 'warning', 'heroicon-m-exclamation-triangle'];
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        $state = $this->form->getState();
        $values = [];
        $clear = array_keys(array_filter((array) ($state['clear'] ?? [])));
        foreach (SettingCatalog::all() as $key => $setting) {
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
        $result = app(SettingChecks::class)->run($group, (string) auth('platform')->user()->email);
        $note = Notification::make()->title(__("platform::settings.groups.{$group}.title"))->body($result['says']);
        ($result['ok'] ? $note->success() : $note->danger())->send();
    }
}
