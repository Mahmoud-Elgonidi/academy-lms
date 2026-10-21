<?php

namespace Modules\Platform\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Modules\Platform\Actions\SaveAiSettings;
use Modules\Platform\Ai\AiProvider;
use Modules\Platform\Ai\PlatformAcademyAi;
use Modules\Platform\Models\AiSettings;
use Modules\Platform\Models\AiUsage;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Authorization\Ability;
use UnitEnum;

/**
 * Settings › AI: each helper on or off, with what it does, who sees what it
 * writes, what is sent to which provider, and what never is; and today's
 * uses against the cap. Owner and admin, when the plan has the AI area.
 *
 * @property-read Schema $form
 */
final class AiSettingsPage extends Page
{
    protected string $view = 'platform::filament.ai-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 86;

    protected static ?string $slug = 'settings/ai';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageAi->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('platform::branding.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('platform::ai.settings.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::ai.settings.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::ai.settings.subtitle');
    }

    public function mount(): void
    {
        $settings = AiSettings::current();
        $this->form->fill(collect(AiFeature::cases())->mapWithKeys(fn (AiFeature $f) => [$f->value => $settings->isOn($f)])->all());
    }

    public function form(Schema $schema): Schema
    {
        $s = 'platform::ai.settings.';
        $provider = __('platform::ai.providers.'.(AiProvider::current()?->value ?? 'claude'));

        return $schema->statePath('data')->components(array_map(fn (AiFeature $f) => Section::make($f->label())
            ->description(__($s.'helpers.'.$f->value.'.what'))
            ->schema([
                Toggle::make($f->value)->label(__($s.'on'))
                    ->helperText(PlatformAcademyAi::configured($f) ? __($s.'helpers.'.$f->value.'.data', ['provider' => $provider]) : __($s.'not_configured')),
            ]), AiFeature::cases()));
    }

    /** @return array{today: int, cap: int} */
    public function usage(): array
    {
        return ['today' => AiUsage::today(), 'cap' => (int) config('platform.ai.daily_cap', 500)];
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        $state = $this->form->getState();
        app(SaveAiSettings::class)->handle(array_map('boolval', $state), (string) auth()->id());
        Notification::make()->success()->title(__('platform::ai.settings.saved'))->send();
    }
}
