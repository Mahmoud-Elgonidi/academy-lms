<?php

namespace Modules\Billing\Filament\Resources\Invoices;

use Carbon\CarbonImmutable;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Models\Invoice;

final class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    public function getTabs(): array
    {
        $now = CarbonImmutable::now();
        $count = fn (callable $scope) => $scope(Invoice::query())->count();

        return [
            'due' => Tab::make(__('billing::invoices.tabs.due'))
                ->badge($count(fn ($q) => $q->where('status', 'issued')) ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('invoices.status', 'issued')),
            'overdue' => Tab::make(__('billing::invoices.tabs.overdue'))
                ->badge($count(fn ($q) => $q->where('status', 'issued')->where('due_at', '<', $now)) ?: null)->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('invoices.status', 'issued')->where('invoices.due_at', '<', $now)),
            'paid' => Tab::make(__('billing::invoices.tabs.paid'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('invoices.status', 'paid')),
            'all' => Tab::make(__('billing::invoices.tabs.all')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'due';
    }
}
