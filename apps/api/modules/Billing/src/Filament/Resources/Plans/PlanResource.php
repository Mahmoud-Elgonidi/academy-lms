<?php

namespace Modules\Billing\Filament\Resources\Plans;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Modules\Billing\Actions\SavePlan;
use Modules\Billing\Domain\BlockTerms;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Models\PriceRegion;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Queries\GroupPriceComparison;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Money\Money;
use UnitEnum;

/**
 * Block plans and their price in each region (docs/architecture/01, "الباقات والأسعار").
 * Plans are switched off rather than deleted: subscriptions point at them.
 */
final class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 80;

    protected static ?string $slug = 'settings/plans';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageBilling->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('billing::plans.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('billing::plans.nav');
    }

    public static function getModelLabel(): string
    {
        return __('billing::plans.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('billing::plans.nav');
    }

    public static function form(Schema $schema): Schema
    {
        $validity = fn (Get $get, Set $set) => $set('validity_days', BlockTerms::defaultValidityDays((int) $get('sessions_per_block'), (int) $get('sessions_per_week')));
        // Its kind and shape are fixed once a student is on the plan: shown, not sent.
        $taken = fn (?Plan $record) => $record !== null && Subscription::query()->where('plan_id', $record->id)->exists();

        $group = fn (Get $get) => $get('lesson_format') === Plan::GROUP;

        return $schema->columns(1)->components([
            // Private or group first: it is what the plan sells, and it cannot change once someone is on it.
            ViewField::make('lesson_format')->label(__('billing::plans.fields.lesson_format'))
                ->view('shared::forms.choice-cards')
                ->viewData(['columns' => 2, 'options' => self::formatCards()])
                ->default(Plan::PRIVATE)->required()->live()
                ->afterStateUpdated(fn (?string $state, Set $set) => $state === Plan::GROUP ? $set('credit_scope', 'enrollment') : null)
                ->helperText(fn (?Plan $record) => $taken($record) ? __('billing::plans.hints.format_locked') : null)
                ->disabled($taken)->dehydrated(fn (?Plan $record) => ! $taken($record)),
            Grid::make(2)->schema([
                TextInput::make('name_ar')->label(__('billing::plans.fields.name_ar'))->required()->maxLength(80),
                TextInput::make('name_en')->label(__('billing::plans.fields.name_en'))->required()->maxLength(80),
            ]),
            ToggleButtons::make('credit_scope')->label(__('billing::plans.fields.credit_scope'))
                ->options(['enrollment' => __('billing::plans.scopes.enrollment'), Plan::SHARED => __('billing::plans.scopes.student_pool')])
                ->inline()->required()->default('enrollment')
                ->helperText(__('billing::plans.hints.credit_scope'))
                // A group plan is never a shared package: each seat is its own course.
                ->hidden($group)
                ->disabled($taken)->dehydrated(fn (?Plan $record, Get $get) => ! $taken($record) && ! $group($get)),
            ToggleButtons::make('session_minutes')->label(__('billing::plans.fields.session_minutes'))
                ->options([30 => '30', 45 => '45', 60 => '60', 90 => '90'])->inline()->required()->default(30)->live()
                ->helperText(fn (?Plan $record) => $taken($record) ? __('billing::plans.hints.shape') : null)
                ->disabled($taken)->dehydrated(fn (?Plan $record) => ! $taken($record)),
            Grid::make(2)->schema([
                Select::make('sessions_per_week')->label(__('billing::plans.fields.sessions_per_week'))
                    ->options(array_combine(range(1, 6), range(1, 6)))->required()->default(2)->native(false)->live()->afterStateUpdated($validity)
                    ->disabled($taken)->dehydrated(fn (?Plan $record) => ! $taken($record)),
                Select::make('sessions_per_block')->label(__('billing::plans.fields.sessions_per_block'))
                    ->options(array_combine([4, 8, 12, 16, 20, 24], [4, 8, 12, 16, 20, 24]))->required()->default(8)->native(false)->live()->afterStateUpdated($validity)
                    ->disabled($taken)->dehydrated(fn (?Plan $record) => ! $taken($record)),
                TextInput::make('validity_days')->label(__('billing::plans.fields.validity_days'))
                    ->numeric()->minValue(1)->maxValue(365)->default(42)->suffix(__('billing::plans.days'))
                    ->helperText(__('billing::plans.hints.validity')),
                Select::make('renew_at_sessions')->label(__('billing::plans.fields.renew_at_sessions'))
                    ->options([1 => 1, 2 => 2, 3 => 3, 4 => 4])->default(2)->native(false)
                    ->helperText(__('billing::plans.hints.renew')),
            ]),
            Repeater::make('prices')->label(__('billing::plans.fields.prices'))
                ->schema([
                    Select::make('price_region_id')->label(__('billing::plans.fields.region'))
                        ->options(fn () => PriceRegion::query()->orderBy('sort')->get()->mapWithKeys(fn (PriceRegion $r) => [$r->id => $r->name().' · '.$r->currency]))
                        ->required()->native(false)->distinct()->live(),
                    TextInput::make('amount')->label(__('billing::plans.fields.amount'))->numeric()->minValue(0)->required()->live(onBlur: true),
                ])
                ->columns(2)->defaultItems(0)->addActionLabel(__('billing::plans.add_price'))->reorderable(false)->live(),
            // A group lesson is cheaper than a private one: how much, per region, against the private plan of the same shape.
            Text::make(fn (Get $get, ?Plan $record) => self::comparison($get, $record))
                ->visible(fn (Get $get, ?Plan $record) => $group($get) && self::comparison($get, $record) !== '')->key('compare')
                ->color('info'),
            Toggle::make('is_active')->label(__('billing::plans.fields.is_active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('prices.region'))
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label(__('billing::plans.fields.name'))
                    ->state(fn (Plan $p) => $p->name())
                    ->description(fn (Plan $p) => ($p->isShared() ? __('billing::plans.scopes.student_pool').' · ' : '').__('billing::plans.summary', [
                        'minutes' => $p->session_minutes,
                        'per_week' => trans_choice('billing::plans.per_week', $p->sessions_per_week, ['n' => $p->sessions_per_week]),
                        'block' => $p->sessions_per_block,
                    ]))
                    ->weight('semibold'),
                TextColumn::make('lesson_format')->label(__('billing::plans.fields.lesson_format'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('billing::plans.formats.'.$state.'.title'))
                    ->icon(fn (string $state) => $state === Plan::GROUP ? Heroicon::OutlinedUserGroup : Heroicon::OutlinedUser)
                    ->color(fn (string $state) => $state === Plan::GROUP ? 'info' : 'gray'),
                TextColumn::make('prices')->label(__('billing::plans.fields.prices'))
                    ->state(fn (Plan $p) => $p->prices->map(fn (PlanPrice $price) => $price->region->name().': '.$price->money()->format())->all() ?: [__('billing::plans.no_prices')])
                    ->badge()->color(fn (Plan $p) => $p->prices->isEmpty() ? 'danger' : 'gray')
                    ->wrap()->limitList(3)->expandableLimitedList(),
                TextColumn::make('validity_days')->label(__('billing::plans.fields.validity_days'))
                    ->formatStateUsing(fn (int $state) => __('billing::plans.valid_for', ['days' => $state])),
                TextColumn::make('renew_at_sessions')->label(__('billing::plans.fields.renew_at_sessions'))
                    ->formatStateUsing(fn (int $state) => trans_choice('billing::plans.renew_when', $state, ['n' => $state])),
                IconColumn::make('is_active')->label(__('billing::plans.fields.is_active'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('lesson_format')->label(__('billing::plans.fields.lesson_format'))
                    ->options([Plan::PRIVATE => __('billing::plans.formats.private.title'), Plan::GROUP => __('billing::plans.formats.group.title')]),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Plan $record) => $data + [
                        'prices' => $record->prices->map(fn (PlanPrice $p) => ['price_region_id' => $p->price_region_id, 'amount' => Money::of($p->amount_minor, $p->currency)->decimal()])->all(),
                    ])
                    ->using(fn (Model $record, array $data) => app(SavePlan::class)->handle($record, self::saved($data))),
                Action::make('toggle')
                    ->label(fn (Plan $p) => $p->is_active ? __('billing::plans.deactivate') : __('billing::plans.activate'))
                    ->icon(fn (Plan $p) => $p->is_active ? Heroicon::OutlinedPause : Heroicon::OutlinedPlay)
                    ->color('gray')
                    ->action(fn (Plan $p) => app(SavePlan::class)->handle($p, ['is_active' => ! $p->is_active] + $p->only(['name_ar', 'name_en', 'session_minutes', 'sessions_per_block', 'sessions_per_week', 'validity_days', 'renew_at_sessions', 'lesson_format', 'credit_scope']) + [
                        'prices' => $p->prices->map(fn (PlanPrice $price) => ['price_region_id' => $price->price_region_id, 'amount' => Money::of($price->amount_minor, $price->currency)->decimal()])->all(),
                    ])),
            ])
            ->emptyStateHeading(__('billing::plans.empty.title'))
            ->emptyStateDescription(__('billing::plans.empty.hint'));
    }

    /** The two formats as the form's cards: what each means for the lesson. */
    public static function formatCards(): array
    {
        return [
            ['value' => Plan::PRIVATE, 'icon' => 'heroicon-o-user', 'title' => __('billing::plans.formats.private.title'), 'line' => __('billing::plans.formats.private.line')],
            ['value' => Plan::GROUP, 'icon' => 'heroicon-o-user-group', 'tone' => 'group', 'title' => __('billing::plans.formats.group.title'),
                'line' => __('billing::plans.formats.group.line', ['max' => 20])],
        ];
    }

    /**
     * The form's data as SavePlan takes it: a group plan is always per course.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function saved(array $data): array
    {
        if (($data['lesson_format'] ?? null) === Plan::GROUP) {
            $data['credit_scope'] = 'enrollment';
        }

        return $data;
    }

    /** "A group lesson in the Gulf: 30 SAR, 35% less than on «8 lessons» (46 SAR)", one line per region priced. */
    private static function comparison(Get $get, ?Plan $record): HtmlString|string
    {
        $rows = app(GroupPriceComparison::class)->handle((int) $get('session_minutes'), (int) $get('sessions_per_week'), (int) $get('sessions_per_block'),
            array_values((array) ($get('prices') ?? [])), $record?->id);

        if ($rows === []) {
            return '';
        }

        return new HtmlString(implode('<br>', array_map(fn (array $r) => e(__($r['pct'] > 0 ? 'billing::plans.compare.less' : ($r['pct'] < 0 ? 'billing::plans.compare.more' : 'billing::plans.compare.same'), [
            'region' => $r['region'], 'lesson' => $r['group_lesson']->format(), 'pct' => abs($r['pct']), 'plan' => $r['private'], 'private' => $r['private_lesson']->format(),
        ])), $rows)));
    }

    public static function getPages(): array
    {
        return ['index' => ManagePlans::route('/')];
    }
}
