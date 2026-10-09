<?php

namespace Modules\Billing\Filament\Resources\Plans;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Modules\Billing\Actions\Discounts\SaveSiblingDiscount;
use Modules\Billing\Actions\SaveBillingSettings;
use Modules\Billing\Actions\SavePlan;
use Modules\Billing\Actions\SavePriceRegions;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\PriceRegion;
use Modules\SharedKernel\Tenancy\TenantContext;

final class ManagePlans extends ManageRecords
{
    protected static string $resource = PlanResource::class;

    public const CURRENCIES = ['SAR', 'AED', 'KWD', 'QAR', 'BHD', 'OMR', 'JOD', 'EGP', 'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'MYR', 'TRY'];

    public function getSubheading(): string|Htmlable|null
    {
        return __('billing::plans.subtitle');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regions')
                ->label(__('billing::plans.regions.action'))
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('gray')
                ->modalHeading(__('billing::plans.regions.title'))
                ->modalDescription(__('billing::plans.regions.hint'))
                ->fillForm(fn () => ['regions' => PriceRegion::query()->orderBy('sort')->get()->map(fn (PriceRegion $r) => $r->only(['id', 'name_ar', 'name_en', 'currency', 'is_default']))->all()])
                ->schema([
                    Repeater::make('regions')->hiddenLabel()
                        ->schema([
                            TextInput::make('id')->hidden(),
                            TextInput::make('name_ar')->label(__('billing::plans.fields.name_ar'))->required()->maxLength(60),
                            TextInput::make('name_en')->label(__('billing::plans.fields.name_en'))->required()->maxLength(60),
                            Select::make('currency')->label(__('billing::plans.fields.currency'))
                                ->options(array_combine(self::CURRENCIES, self::CURRENCIES))->required()->native(false)->searchable(),
                            Toggle::make('is_default')->label(__('billing::plans.fields.is_default'))->inline(false),
                        ])
                        ->columns(4)->defaultItems(1)->addActionLabel(__('billing::plans.regions.add'))->reorderable(),
                ])
                ->action(function (array $data) {
                    app(SavePriceRegions::class)->handle(array_values($data['regions']));
                    Notification::make()->success()->title(__('billing::plans.saved'))->send();
                }),
            Action::make('tax')
                ->label(__('billing::plans.tax.action'))
                ->icon(Heroicon::OutlinedReceiptPercent)
                ->color('gray')
                ->modalHeading(__('billing::plans.tax.title'))
                ->fillForm(function () {
                    $s = BillingSettings::current();

                    return ['tax_percent' => $s->tax_rate_bp / 100, 'prices_include_tax' => $s->prices_include_tax, 'tax_number' => $s->tax_number, 'invoice_prefix' => $s->invoice_prefix, 'due_days' => $s->due_days,
                        'seller_name' => $s->seller_name ?: app(TenantContext::class)->require()->nameAr, 'zatca' => $s->zatca];
                })
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('tax_percent')->label(__('billing::plans.tax.rate'))->numeric()->minValue(0)->maxValue(100)->suffix('%')->required(),
                        TextInput::make('tax_number')->label(__('billing::plans.tax.number'))->maxLength(30),
                        TextInput::make('invoice_prefix')->label(__('billing::plans.tax.prefix'))->maxLength(12)->required(),
                        TextInput::make('due_days')->label(__('billing::plans.tax.due_days'))->numeric()->minValue(0)->maxValue(90)->required(),
                    ]),
                    Toggle::make('prices_include_tax')->label(__('billing::plans.tax.inclusive'))->helperText(__('billing::plans.tax.inclusive_hint')),
                    TextInput::make('seller_name')->label(__('billing::plans.tax.seller_name'))->helperText(__('billing::plans.tax.seller_hint'))->maxLength(120),
                    Toggle::make('zatca')->label(__('billing::plans.tax.zatca'))->helperText(__('billing::plans.tax.zatca_hint')),
                ])
                ->action(function (array $data) {
                    app(SaveBillingSettings::class)->handle($data['tax_percent'], (bool) $data['prices_include_tax'], $data['tax_number'] ?? null, (string) $data['invoice_prefix'], (int) $data['due_days'],
                        $data['seller_name'] ?? null, (bool) ($data['zatca'] ?? false));
                    Notification::make()->success()->title(__('billing::plans.saved'))->send();
                }),
            Action::make('sibling')
                ->label(__('billing::discounts.sibling.action'))
                ->icon(Heroicon::OutlinedUsers)
                ->color('gray')
                ->modalHeading(__('billing::discounts.sibling.title'))
                ->modalDescription(__('billing::discounts.sibling.hint'))
                ->modalWidth('md')
                ->fillForm(fn () => ['second' => BillingSettings::current()->sibling_second_bp / 100, 'more' => BillingSettings::current()->sibling_more_bp / 100,
                    'family_days' => BillingSettings::current()->family_invoice_days])
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('second')->label(__('billing::discounts.sibling.second'))->numeric()->minValue(0)->maxValue(100)->suffix('%')->required(),
                        TextInput::make('more')->label(__('billing::discounts.sibling.more'))->numeric()->minValue(0)->maxValue(100)->suffix('%')->required(),
                    ]),
                    TextInput::make('family_days')->label(__('billing::discounts.sibling.family_days'))->helperText(__('billing::discounts.sibling.family_days_hint'))
                        ->numeric()->integer()->minValue(0)->maxValue(14)->suffix(__('billing::discounts.sibling.days'))->required(),
                ])
                ->action(function (array $data) {
                    app(SaveSiblingDiscount::class)->handle($data['second'], $data['more'], $data['family_days']);
                    Notification::make()->success()->title(__('billing::discounts.sibling.saved'))->send();
                }),
            CreateAction::make()
                ->label(__('billing::plans.create'))
                ->using(fn (array $data) => app(SavePlan::class)->handle(null, $data)),
        ];
    }
}
