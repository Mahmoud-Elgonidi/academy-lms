<?php

namespace Modules\Payroll\Filament\Resources\RateCards;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Payroll\Actions\SaveRateCard;
use Modules\Payroll\Domain\RateLine;
use Modules\Payroll\Models\RateCard;
use Modules\Payroll\Models\RateCardLine;
use Modules\Payroll\Models\TeacherRateCard;
use Modules\Payroll\Queries\PayrollTeachers;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Money\Money;
use UnitEnum;

/**
 * Rate cards (docs/architecture/01, section 13: "جدول الأسعار"): a price
 * by the hour or by the lesson for each subject and kind of lesson, in one
 * currency, and the teachers paid from it.
 */
final class RateCardResource extends Resource
{
    protected static ?string $model = RateCard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 81;

    protected static ?string $slug = 'settings/teacher-rates';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManagePayroll->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('payroll::rates.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('payroll::rates.nav');
    }

    public static function getModelLabel(): string
    {
        return __('payroll::rates.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payroll::rates.nav');
    }

    /** @return array<string, string> */
    private static function subjects(): array
    {
        return DB::table('subjects')->orderBy('name_ar')->pluck(str_starts_with(app()->getLocale(), 'ar') ? 'name_ar' : 'name_en', 'id')->all();
    }

    public static function form(Schema $schema): Schema
    {
        $l = 'payroll::rates.';

        return $schema->columns(1)->components([
            Grid::make(3)->schema([
                TextInput::make('name')->label(__($l.'fields.name'))->required()->maxLength(80)->columnSpan(2),
                Select::make('currency')->label(__($l.'fields.currency'))->required()->native(false)->searchable()
                    ->options(array_combine(SaveRateCard::CURRENCIES, SaveRateCard::CURRENCIES))->default('EGP'),
            ]),
            Toggle::make('is_default')->label(__($l.'fields.is_default'))->helperText(__($l.'fields.is_default_hint')),
            Repeater::make('lines')->label(__($l.'fields.lines'))
                ->schema([
                    Select::make('subject_id')->label(__($l.'fields.subject'))->placeholder(__($l.'fields.any_subject'))->native(false)
                        ->options(fn () => self::subjects()),
                    Select::make('session_type')->label(__($l.'fields.type'))->placeholder(__($l.'fields.any_type'))->native(false)
                        ->options(collect(SaveRateCard::SESSION_TYPES)->mapWithKeys(fn (string $t) => [$t => __('payroll::payroll.types.'.$t)])->all()),
                    // A group lesson's fixed rate, whatever its students; without one it is paid as private.
                    ToggleButtons::make('session_format')->label(__($l.'fields.format'))->inline()->default(RateLine::PRIVATE)
                        ->options(collect(RateLine::FORMATS)->mapWithKeys(fn (string $f) => [$f => __($l.'formats.'.$f)])->all()),
                    ToggleButtons::make('unit')->label(__($l.'fields.unit'))->inline()->required()->default('hour')
                        ->options(collect(RateLine::UNITS)->mapWithKeys(fn (string $u) => [$u => __($l.'units.'.$u)])->all()),
                    TextInput::make('amount')->label(__($l.'fields.amount'))->numeric()->minValue(0)->required(),
                ])
                ->columns(5)->defaultItems(1)->addActionLabel(__($l.'add_line'))->reorderable(false),
            Select::make('teachers')->label(__($l.'fields.teachers'))->helperText(__($l.'fields.teachers_hint'))
                ->multiple()->native(false)->searchable()->options(fn () => app(PayrollTeachers::class)->options()),
        ]);
    }

    public static function table(Table $table): Table
    {
        $l = 'payroll::rates.';
        $subjects = self::subjects();
        $teachers = app(PayrollTeachers::class)->options();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['lines', 'teachers']))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__($l.'columns.name'))->weight('semibold')
                    ->description(fn (RateCard $c) => $c->currency.($c->is_default ? ' · '.__($l.'default') : '')),
                TextColumn::make('lines')->label(__($l.'columns.lines'))
                    ->state(fn (RateCard $c) => $c->lines->map(fn (RateCardLine $line) => self::describe($line, $c->currency, $subjects))->all() ?: [__($l.'no_lines')])
                    ->badge()->color(fn (RateCard $c) => $c->lines->isEmpty() ? 'danger' : 'gray')
                    ->wrap()->limitList(4)->expandableLimitedList(),
                TextColumn::make('teachers')->label(__($l.'columns.teachers'))
                    ->state(fn (RateCard $c) => [
                        ...$c->teachers->pluck('teacher_id')->map(fn (string $id) => $teachers[$id] ?? null)->filter()->sort()->values()->all(),
                        ...($c->is_default ? [__($l.'everyone_else')] : []),
                    ] ?: ['—'])
                    ->badge()->color('gray')->wrap()->limitList(4)->expandableLimitedList(),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, RateCard $record) => $data + [
                        'lines' => $record->lines->map(fn (RateCardLine $line) => [
                            'subject_id' => $line->subject_id, 'session_type' => $line->session_type, 'session_format' => $line->session_format, 'unit' => $line->unit,
                            'amount' => Money::of($line->amount_minor, $record->currency)->decimal(),
                        ])->all(),
                        'teachers' => TeacherRateCard::query()->where('rate_card_id', $record->id)->pluck('teacher_id')->all(),
                    ])
                    ->using(fn (Model $record, array $data, Action $action) => self::save($record, $data, $action)),
                Action::make('delete')->label(__('filament-actions::delete.single.label'))->icon(Heroicon::OutlinedTrash)->color('danger')
                    ->visible(fn (RateCard $c) => ! $c->is_default)
                    ->requiresConfirmation()
                    ->action(fn (RateCard $c) => app(SaveRateCard::class)->delete($c)),
            ])
            ->emptyStateHeading(__($l.'empty.title'))
            ->emptyStateDescription(__($l.'empty.hint'));
    }

    /** @param  array<string, mixed>  $data */
    public static function save(?RateCard $card, array $data, Action $action): ?RateCard
    {
        try {
            $saved = app(SaveRateCard::class)->handle($card, $data);
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
            $action->halt();

            return null;
        }

        return $saved;
    }

    /** @param  array<string, string>  $subjects */
    private static function describe(RateCardLine $line, string $currency, array $subjects): string
    {
        $what = implode(' · ', array_filter([
            $line->subject_id ? ($subjects[$line->subject_id] ?? '') : null,
            $line->session_type ? __('payroll::payroll.types.'.$line->session_type) : null,
            $line->session_format === RateLine::GROUP ? __('payroll::rates.formats.group') : null,
        ])) ?: __('payroll::payroll.mine.any');

        return $what.': '.__('payroll::payroll.units.'.$line->unit, ['amount' => Money::of($line->amount_minor, $currency)->format()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRateCards::route('/')];
    }
}
