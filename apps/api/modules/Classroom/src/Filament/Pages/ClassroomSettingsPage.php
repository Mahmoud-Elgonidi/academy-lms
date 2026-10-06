<?php

namespace Modules\Classroom\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Modules\Classroom\Actions\SaveClassroomSettings;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\SharedKernel\Authorization\Ability;
use UnitEnum;

/**
 * Where the academy's lessons happen: its own classroom (LiveKit), or each
 * teacher's outside link as the fallback (docs/architecture/03, section 6).
 *
 * @property-read Schema $form
 */
final class ClassroomSettingsPage extends Page
{
    protected string $view = 'classroom::filament.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static ?int $navigationSort = 85;

    protected static ?string $slug = 'settings/classroom';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageClassroom->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('platform::branding.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('classroom::classroom.settings.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('classroom::classroom.settings.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('classroom::classroom.settings.subtitle');
    }

    public function mount(): void
    {
        $this->form->fill(['provider' => ClassroomSettings::current()->provider]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Radio::make('provider')->label(__('classroom::classroom.settings.provider'))->required()
                ->options(collect(ClassroomSettings::PROVIDERS)->mapWithKeys(fn ($p) => [$p => __('classroom::classroom.settings.providers.'.$p)])),
        ]);
    }

    public function save(): void
    {
        app(SaveClassroomSettings::class)->handle($this->form->getState()['provider']);
        Notification::make()->success()->title(__('classroom::classroom.settings.saved'))->send();
    }
}
