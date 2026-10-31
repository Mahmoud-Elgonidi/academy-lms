<?php

namespace Modules\Quran\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Quran\Actions\Hifz\SaveHifzDailyPages;
use Modules\Quran\Models\QuranSettings;
use Modules\SharedKernel\Authorization\Ability;
use UnitEnum;

/**
 * The Quran › Settings (owner and admin, while the plan has the Quran): how
 * many pages a learner's daily hifz review holds at most. More settings
 * (programs, mistakes and grades, exams, certificates) join it later.
 *
 * @property-read Schema $form
 */
final class QuranSettingsPage extends Page
{
    protected string $view = 'quran::filament.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 49;

    protected static ?string $slug = 'quran/settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageQuran->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('quran::quran.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('quran::quran.settings.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('quran::quran.settings.title');
    }

    public function mount(): void
    {
        $this->form->fill(['hifz_daily_pages' => QuranSettings::current()->hifz_daily_pages]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            TextInput::make('hifz_daily_pages')->label(__('quran::hifz.settings.daily'))->helperText(__('quran::hifz.settings.hint'))
                ->numeric()->minValue(1)->maxValue(SaveHifzDailyPages::MAX)->required()->extraInputAttributes(['style' => 'max-inline-size: 8rem']),
        ]);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        $data = $this->form->getState();
        try {
            app(SaveHifzDailyPages::class)->handle((int) $data['hifz_daily_pages']);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['data.hifz_daily_pages' => $e->errors()['hifz_daily_pages'] ?? []]);
        }
        Notification::make()->success()->title(__('quran::hifz.settings.saved'))->send();
    }
}
