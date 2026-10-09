<?php

namespace Modules\Billing\Filament;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\VoidInvoice;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Time\ViewerTimezone;

/** The invoice actions, shared by the list and the invoice page. */
final class InvoiceActions
{
    public static function recordPayment(): Action
    {
        return Action::make('recordPayment')
            ->label(__('billing::invoices.pay.action'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('primary')
            ->visible(fn (Invoice $record) => $record->status === 'issued' && Gate::allows(Ability::RecordPayments->value))
            ->modalHeading(fn (Invoice $record) => __('billing::invoices.pay.title', ['number' => $record->number]))
            ->modalDescription(fn (Invoice $record) => __('billing::invoices.pay.hint', ['amount' => $record->outstanding()->format()]))
            ->modalSubmitActionLabel(__('billing::invoices.pay.submit'))
            ->fillForm(fn (Invoice $record) => [
                'method' => 'bank_transfer',
                'amount' => $record->outstanding()->decimal(),
                'received_at' => CarbonImmutable::now()->utc()->format('Y-m-d H:i:s'),
            ])
            ->schema(fn (Invoice $record) => [
                ToggleButtons::make('method')->label(__('billing::invoices.pay.method'))
                    ->options(collect(Payment::METHODS)->mapWithKeys(fn ($m) => [$m => __('billing::billing.methods.'.$m)])->all())
                    ->inline()->required(),
                Grid::make(2)->schema([
                    TextInput::make('amount')->label(__('billing::invoices.pay.amount'))->numeric()->minValue(0)->required()->suffix($record->currency),
                    DateTimePicker::make('received_at')->label(__('billing::invoices.pay.received_at'))->seconds(false)->native(false)->required()
                        ->timezone(app(ViewerTimezone::class)->effective()),
                ]),
                TextInput::make('reference')->label(__('billing::invoices.pay.reference'))->maxLength(120)->placeholder(__('billing::invoices.pay.reference_hint')),
                Textarea::make('note')->label(__('billing::invoices.pay.note'))->rows(2),
            ])
            ->action(function (Invoice $record, array $data) {
                $invoice = app(RecordPayment::class)->handle(
                    $record->id,
                    $data['method'],
                    Money::parse($data['amount'], $record->currency),
                    $data['reference'] ?: null,
                    CarbonImmutable::parse($data['received_at'], 'UTC'),
                    $data['note'] ?: null,
                    (string) auth()->id(),
                );
                Notification::make()->success()
                    ->title($invoice->status === 'paid' ? __('billing::invoices.pay.paid', ['number' => $invoice->number]) : __('billing::invoices.pay.partial', ['left' => $invoice->outstanding()->format()]))
                    ->body($invoice->status === 'paid' && $invoice->subscriptionIds() !== [] ? __('billing::invoices.pay.paid_hint') : null)
                    ->send();
            });
    }

    public static function void(): Action
    {
        return Action::make('void')
            ->label(__('billing::invoices.void.action'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (Invoice $record) => $record->status === 'issued' && $record->paid_minor === 0 && Gate::allows(Ability::ManageBilling->value))
            ->requiresConfirmation()
            ->modalHeading(fn (Invoice $record) => __('billing::invoices.void.title', ['number' => $record->number]))
            ->modalDescription(__('billing::invoices.void.hint'))
            ->schema([Textarea::make('reason')->label(__('billing::invoices.void.reason'))->required()->maxLength(200)])
            ->action(function (Invoice $record, array $data) {
                app(VoidInvoice::class)->handle($record->id, $data['reason'], (string) auth()->id());
                Notification::make()->success()->title(__('billing::invoices.void.done'))->send();
            });
    }
}
