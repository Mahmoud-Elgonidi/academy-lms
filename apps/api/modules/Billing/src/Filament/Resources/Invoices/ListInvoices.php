<?php

namespace Modules\Billing\Filament\Resources\Invoices;

use Carbon\CarbonImmutable;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentProof;

final class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    public function getTabs(): array
    {
        $now = CarbonImmutable::now();
        $count = fn (callable $scope, ?Builder $from = null) => $scope($from ?? Invoice::query())->count();

        $proofs = $count(fn ($q) => $q->where('status', 'pending'), PaymentProof::query());

        return [
            'proofs' => Tab::make(__('billing::proofs.review.tab'))
                ->badge($proofs ?: null)->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => InvoiceResource::withProofWaiting($query)),
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

    /** Proofs waiting come first: the money may already be in the account. */
    public function getDefaultActiveTab(): string|int|null
    {
        return PaymentProof::query()->where('status', 'pending')->exists() ? 'proofs' : 'due';
    }
}
