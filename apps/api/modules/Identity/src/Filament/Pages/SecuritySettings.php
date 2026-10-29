<?php

namespace Modules\Identity\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Actions\SaveSecuritySettings;
use Modules\Identity\Models\IdentitySettings;
use Modules\SharedKernel\Authorization\Ability;
use UnitEnum;

/**
 * Settings › Security (the owner alone; docs/architecture/02, section 19):
 * whether two-step sign-in is required of the owner, the admins and the
 * accountants, with what it guards and what turning it off means. Saves
 * through SaveSecuritySettings.
 *
 * @property-read Schema $form
 */
final class SecuritySettings extends Page
{
    protected string $view = 'identity::security.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 81;

    protected static ?string $slug = 'settings/security';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageSecurity->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('platform::branding.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('identity::security.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('identity::security.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('identity::security.subtitle');
    }

    public function mount(): void
    {
        $this->form->fill(['require_two_factor' => IdentitySettings::current()->require_two_factor]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Toggle::make('require_two_factor')
                ->label(__('identity::security.require'))
                ->helperText(__('identity::security.require_hint'))
                ->live(),
            Callout::make(__('identity::security.why_title'))->info()
                ->description(__('identity::security.why')),
            Callout::make(__('identity::security.off_title'))->warning()
                ->description(__('identity::security.off'))
                ->visible(fn (Get $get): bool => ! $get('require_two_factor')),
        ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        try {
            app(SaveSecuritySettings::class)->handle((bool) ($this->form->getState()['require_two_factor'] ?? true));
        } catch (ValidationException $e) {
            Notification::make()->danger()->title((string) collect($e->errors())->flatten()->first())->send();

            return;
        }
        Notification::make()->success()->title(__('identity::security.saved'))->send();
    }
}
