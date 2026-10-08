<?php

namespace Modules\Billing\Filament\Resources\Coupons;

use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\Discounts\SaveCoupon;
use Modules\Billing\Filament\Resources\Plans\ManagePlans;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Models\Plan;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\TenantContext;
use UnitEnum;

/**
 * Coupons (docs/architecture/02, section 3, "الخصومات"): the codes the
 * academy gives out, what each takes off and for how many invoices, its
 * dates and uses. Turned off rather than deleted: invoices point at them.
 */
final class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?int $navigationSort = 81;

    protected static ?string $slug = 'settings/coupons';

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
        return __('billing::discounts.coupons.nav');
    }

    public static function getModelLabel(): string
    {
        return __('billing::discounts.coupons.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('billing::discounts.coupons.nav');
    }

    public static function form(Schema $schema): Schema
    {
        $l = 'billing::discounts.coupons.fields.';

        return $schema->columns(1)->components([
            Grid::make(2)->schema([
                TextInput::make('code')->label(__($l.'code'))->helperText(__($l.'code_hint'))->required()->maxLength(32)
                    ->extraInputAttributes(['dir' => 'ltr', 'style' => 'text-transform: uppercase']),
                TextInput::make('name')->label(__($l.'name'))->maxLength(80),
            ]),
            ToggleButtons::make('kind')->label(__($l.'kind'))->inline()->required()->default('percent')->live()
                ->options(['percent' => __($l.'kinds.percent'), 'amount' => __($l.'kinds.amount')]),
            Grid::make(2)->schema([
                TextInput::make('percent')->label(__($l.'percent'))->numeric()->minValue(0.01)->maxValue(100)->suffix('%')
                    ->required(fn (Get $get) => $get('kind') === 'percent')->visible(fn (Get $get) => $get('kind') === 'percent'),
                TextInput::make('amount')->label(__($l.'amount'))->numeric()->minValue(0)
                    ->required(fn (Get $get) => $get('kind') === 'amount')->visible(fn (Get $get) => $get('kind') === 'amount'),
                Select::make('currency')->label(__($l.'currency'))->native(false)->searchable()
                    ->options(array_combine(ManagePlans::CURRENCIES, ManagePlans::CURRENCIES))
                    ->required(fn (Get $get) => $get('kind') === 'amount')->visible(fn (Get $get) => $get('kind') === 'amount'),
            ]),
            Grid::make(2)->schema([
                TextInput::make('blocks')->label(__($l.'blocks'))->helperText(__($l.'blocks_hint'))->numeric()->integer()->minValue(1)->maxValue(120),
                TextInput::make('max_uses')->label(__($l.'max_uses'))->helperText(__($l.'max_uses_hint'))->numeric()->integer()->minValue(1),
                DatePicker::make('starts_on')->label(__($l.'starts_on'))->native(false),
                DatePicker::make('ends_on')->label(__($l.'ends_on'))->native(false)->afterOrEqual('starts_on'),
            ]),
            Select::make('plan_ids')->label(__($l.'plans'))->helperText(__($l.'plans_hint'))->multiple()->native(false)
                ->options(fn () => Plan::query()->orderBy('sort')->orderBy('session_minutes')->get()->mapWithKeys(fn (Plan $p) => [$p->id => $p->name()])->all()),
            Toggle::make('with_sibling')->label(__($l.'with_sibling'))->helperText(__($l.'with_sibling_hint')),
            Toggle::make('is_active')->label(__($l.'is_active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        $c = 'billing::discounts.coupons.';

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->label(__($c.'columns.code'))->weight('semibold')->searchable()
                    ->description(fn (Coupon $coupon) => $coupon->name)->fontFamily('mono'),
                TextColumn::make('value')->label(__($c.'columns.value'))->state(fn (Coupon $coupon) => $coupon->valueLabel())
                    ->description(fn (Coupon $coupon) => $coupon->with_sibling ? __($c.'fields.with_sibling') : null),
                TextColumn::make('blocks')->label(__($c.'columns.blocks'))
                    ->state(fn (Coupon $coupon) => match ($coupon->blocks) {
                        null => __($c.'every'),
                        1 => __($c.'first'),
                        default => __($c.'blocks_n', ['n' => $coupon->blocks]),
                    }),
                TextColumn::make('dates')->label(__($c.'columns.dates'))->state(fn (Coupon $coupon) => self::dates($coupon)),
                TextColumn::make('uses')->label(__($c.'columns.uses'))
                    ->state(fn (Coupon $coupon) => $coupon->max_uses === null ? (string) $coupon->uses() : __($c.'uses_of', ['uses' => $coupon->uses(), 'max' => $coupon->max_uses])),
                TextColumn::make('status')->label(__($c.'columns.status'))->badge()
                    ->state(fn (Coupon $coupon) => __($c.'statuses.'.self::status($coupon)))
                    ->color(fn (Coupon $coupon) => match (self::status($coupon)) {
                        'on' => 'success',
                        'off' => 'gray',
                        'upcoming' => 'info',
                        default => 'warning',
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__($c.'edit'))
                    ->modalWidth('2xl')
                    ->mutateRecordDataUsing(fn (array $data, Coupon $record) => $data + [
                        'percent' => $record->percent_bp === null ? null : $record->percent_bp / 100,
                        'amount' => $record->amount_minor === null ? null : Money::of($record->amount_minor, (string) $record->currency)->decimal(),
                    ])
                    ->using(fn (Model $record, array $data) => self::attempt(fn () => app(SaveCoupon::class)->handle($record->getKey(), self::data($data), (string) auth()->id())))
                    ->successNotificationTitle(__($c.'saved')),
            ])
            ->emptyStateHeading(__($c.'empty'))
            ->emptyStateIcon(Heroicon::OutlinedTicket);
    }

    /** @return array<string, mixed> the form's values as SaveCoupon takes them */
    public static function data(array $data): array
    {
        return [
            'code' => (string) ($data['code'] ?? ''),
            'name' => $data['name'] ?? null,
            'kind' => (string) ($data['kind'] ?? 'percent'),
            'percent' => $data['percent'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'blocks' => $data['blocks'] ?? null,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'max_uses' => $data['max_uses'] ?? null,
            'plan_ids' => array_values($data['plan_ids'] ?? []),
            'with_sibling' => (bool) ($data['with_sibling'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * Runs a save; what it refuses is said in a notification and the modal
     * stays open with what was typed.
     *
     * @template T
     *
     * @param  Closure(): T  $save
     * @return T
     */
    public static function attempt(Closure $save): mixed
    {
        try {
            return $save();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(implode(' ', $e->validator->errors()->all()))->send();

            throw new Halt;
        }
    }

    /** on, off, upcoming (before its first day), ended (past its last day) or used up */
    public static function status(Coupon $coupon): string
    {
        $today = CarbonImmutable::now(app(TenantContext::class)->require()->defaultTimezone)->toDateString();

        return match (true) {
            ! $coupon->is_active => 'off',
            $coupon->starts_on !== null && $coupon->starts_on->toDateString() > $today => 'upcoming',
            $coupon->ends_on !== null && $coupon->ends_on->toDateString() < $today => 'ended',
            $coupon->max_uses !== null && $coupon->uses() >= $coupon->max_uses => 'used_up',
            default => 'on',
        };
    }

    private static function dates(Coupon $coupon): string
    {
        $c = 'billing::discounts.coupons.';
        $fmt = fn (CarbonImmutable $d) => $d->locale(app()->getLocale())->translatedFormat('j M Y');

        return match (true) {
            $coupon->starts_on !== null && $coupon->ends_on !== null => __($c.'between', ['from' => $fmt($coupon->starts_on), 'to' => $fmt($coupon->ends_on)]),
            $coupon->starts_on !== null => __($c.'from', ['date' => $fmt($coupon->starts_on)]),
            $coupon->ends_on !== null => __($c.'until', ['date' => $fmt($coupon->ends_on)]),
            default => __($c.'any_time'),
        };
    }

    public static function getPages(): array
    {
        return ['index' => ManageCoupons::route('/')];
    }
}
