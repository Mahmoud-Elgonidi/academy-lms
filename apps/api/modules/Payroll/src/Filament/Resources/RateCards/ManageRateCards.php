<?php

namespace Modules\Payroll\Filament\Resources\RateCards;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

final class ManageRateCards extends ManageRecords
{
    protected static string $resource = RateCardResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('payroll::rates.subtitle');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('payroll::rates.create'))
                ->using(fn (array $data, Action $action) => RateCardResource::save(null, $data, $action)),
        ];
    }
}
