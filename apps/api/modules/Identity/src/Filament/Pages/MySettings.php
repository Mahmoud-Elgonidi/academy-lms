<?php

namespace Modules\Identity\Filament\Pages;

use DateTimeZone;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\Identity\Actions\ChangePassword;
use Modules\Identity\Actions\UpdatePreferences;
use Modules\Identity\Actions\UpdateProfile;
use Modules\Identity\Models\User;
use Modules\Identity\Preferences\PreferenceStore;
use SensitiveParameter;

/**
 * "My settings": name, language, timezone, the screen choices modules declare
 * (e.g. the live board layout) and password. Saves through the same Actions
 * the API uses.
 */
final class MySettings extends EditProfile
{
    private ?string $localeBeforeSave = null;

    public static function getLabel(): string
    {
        return __('identity::settings.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('identity::settings.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('identity::settings.sections.account'))
                ->schema([$this->getNameFormComponent()]),

            Section::make(__('identity::settings.sections.region'))
                ->description(__('identity::settings.region_hint'))
                ->schema([
                    Select::make('locale')
                        ->label(__('identity::settings.fields.locale'))
                        ->options(['ar' => 'العربية', 'en' => 'English'])
                        ->required()
                        ->native(false),
                    Select::make('timezone')
                        ->label(__('identity::settings.fields.timezone'))
                        ->options(fn (): array => self::timezoneOptions())
                        ->searchable()
                        ->required()
                        ->helperText(__('identity::settings.timezone_hint')),
                ]),

            Section::make(__('identity::settings.sections.screens'))
                ->schema($this->preferenceComponents())
                ->visible(fn (): bool => $this->store()->definitions() !== []),

            Section::make(__('identity::settings.sections.password'))
                ->schema([
                    $this->getPasswordFormComponent(),
                    $this->getPasswordConfirmationFormComponent(),
                    $this->getCurrentPasswordFormComponent(),
                ]),
        ]);
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->getUser();

        foreach ($this->store()->definitions() as $key => $definition) {
            $data[self::field($key)] = $this->store()->valueFor($user, $key);
        }

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        /** @var User $record */
        $this->localeBeforeSave = $record->locale;

        app(UpdateProfile::class)->handle($record, Arr::only($data, ['name', 'locale', 'timezone']));

        $preferences = [];
        foreach (array_keys($this->store()->definitions()) as $key) {
            if (array_key_exists(self::field($key), $data)) {
                $preferences[$key] = $data[self::field($key)];
            }
        }
        app(UpdatePreferences::class)->handle($record, $preferences);

        if (filled($data['password'] ?? null)) {
            app(ChangePassword::class)->handle($record, $data['password']);
        }

        return $record;
    }

    /** A new language flips the layout direction, so reload the page. */
    protected function getRedirectUrl(): ?string
    {
        return $this->localeBeforeSave !== $this->getUser()->getAttribute('locale') ? self::getUrl() : null;
    }

    /** @return list<ToggleButtons> */
    private function preferenceComponents(): array
    {
        $components = [];

        foreach ($this->store()->definitions() as $key => $definition) {
            $components[] = ToggleButtons::make(self::field($key))
                ->label(__($definition->label))
                ->helperText($definition->hint ? __($definition->hint) : null)
                ->options(array_map(fn (string $label) => __($label), $definition->options))
                ->inline()
                ->required();
        }

        return $components;
    }

    /** Form field names cannot hold dots, which Filament reads as nesting. */
    private static function field(string $key): string
    {
        return 'pref__'.str_replace('.', '__', $key);
    }

    /** @return array<string, array<string, string>> IANA names grouped by region, labelled with today's offset. */
    private static function timezoneOptions(): array
    {
        $groups = [];
        $now = new \DateTimeImmutable('now');

        foreach (DateTimeZone::listIdentifiers() as $id) {
            $offset = (new DateTimeZone($id))->getOffset($now);
            $sign = $offset < 0 ? '−' : '+';
            $label = sprintf('%s (UTC%s%02d:%02d)', str_replace('_', ' ', $id), $sign, intdiv(abs($offset), 3600), intdiv(abs($offset) % 3600, 60));
            $groups[strstr($id, '/', true) ?: $id][$id] = $label;
        }

        return $groups;
    }

    private function store(): PreferenceStore
    {
        return app(PreferenceStore::class);
    }
}
