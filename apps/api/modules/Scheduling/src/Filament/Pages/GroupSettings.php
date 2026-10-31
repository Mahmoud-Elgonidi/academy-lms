<?php

namespace Modules\Scheduling\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Modules\Scheduling\Actions\Groups\SaveGroupSettings;
use Modules\Scheduling\Domain\Groups\GroupRules;
use Modules\Scheduling\Domain\Groups\LateJoin;
use Modules\Scheduling\Domain\Models\SchedulingSettings;
use Modules\SharedKernel\Authorization\Ability;
use UnitEnum;

/**
 * Settings › Group lessons (docs/architecture/02, "الحصص الجماعية"): what a
 * student joining a running group gets for its lessons held (the office
 * picks at join time, this first), whether ages mix, and the students a new
 * group waits for. Saved through SaveGroupSettings.
 *
 * @property-read Schema $form
 */
final class GroupSettings extends Page
{
    protected string $view = 'scheduling::filament.groups.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 86;

    protected static ?string $slug = 'settings/groups';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManagePolicies->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('platform::branding.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('scheduling::group_screens.settings.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('scheduling::group_screens.settings.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('scheduling::group_screens.settings.subtitle');
    }

    public function mount(): void
    {
        $s = SchedulingSettings::current();
        $this->form->fill(['late_join' => $s->late_join, 'allow_mixed_ages' => (bool) $s->allow_mixed_ages, 'group_min_default' => $s->group_min_default]);
    }

    public function form(Schema $schema): Schema
    {
        $w = 'scheduling::group_screens.settings.';

        return $schema->statePath('data')->components([
            Section::make(__($w.'late.title'))->description(__($w.'late.hint'))->schema([
                ViewField::make('late_join')->hiddenLabel()->label(__($w.'late.title'))
                    ->view('shared::forms.choice-cards')
                    ->viewData(['columns' => 2, 'options' => self::lateJoinCards()])
                    ->required()->in(LateJoin::CHOICES),
            ]),
            Section::make(__($w.'ages.title'))->description(__($w.'ages.hint'))->schema([
                Toggle::make('allow_mixed_ages')->label(__($w.'ages.mixed'))->helperText(__($w.'ages.mixed_hint'))->onColor('warning')->live(),
                Callout::make(__($w.'ages.warning_title'))->description(__($w.'ages.warning'))->warning()
                    ->visible(fn (Get $get) => (bool) $get('allow_mixed_ages')),
            ]),
            Section::make(__($w.'start.title'))->description(__($w.'start.hint'))->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([TextInput::make('group_min_default')->label(__($w.'start.min'))->placeholder(__($w.'start.first'))
                    ->helperText(__($w.'start.min_hint'))
                    ->numeric()->integer()->minValue(1)->maxValue(GroupRules::MAX_SIZE)->suffix(__($w.'start.students'))
                    ->columnSpan(1)]),
            ]),
        ]);
    }

    /** The four ways to make up for lessons a late joiner missed, each with what it gives. */
    public static function lateJoinCards(): array
    {
        $w = 'scheduling::group_screens.late_join.';

        return [
            ['value' => LateJoin::DISCOUNT, 'icon' => 'heroicon-o-receipt-percent', 'title' => __($w.'discount.title'), 'line' => __($w.'discount.line')],
            ['value' => LateJoin::PRIVATE, 'icon' => 'heroicon-o-user', 'title' => __($w.'private.title'), 'line' => __($w.'private.line', ['days' => LateJoin::MAKEUP_DAYS])],
            ['value' => LateJoin::GROUP, 'icon' => 'heroicon-o-user-group', 'tone' => 'group', 'title' => __($w.'group.title'), 'line' => __($w.'group.line', ['days' => LateJoin::MAKEUP_DAYS])],
            ['value' => LateJoin::NONE, 'icon' => 'heroicon-o-minus-circle', 'title' => __($w.'none.title'), 'line' => __($w.'none.line')],
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $min = $data['group_min_default'] ?? null;
        app(SaveGroupSettings::class)->handle((string) $data['late_join'], (bool) ($data['allow_mixed_ages'] ?? false), $min === null || $min === '' ? null : (int) $min);
        Notification::make()->success()->title(__('scheduling::group_screens.settings.saved'))->send();
    }
}
