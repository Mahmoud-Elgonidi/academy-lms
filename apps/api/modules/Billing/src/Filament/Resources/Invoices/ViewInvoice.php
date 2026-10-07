<?php

namespace Modules\Billing\Filament\Resources\Invoices;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Modules\Billing\Filament\InvoiceActions;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Payments\PayLinks;
use Modules\Billing\Queries\InvoiceDocument;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * One invoice as the payer receives it, printable, with what has been paid
 * and the actions that apply: its pay link (to send by hand), and each go
 * at paying it online. A Saudi tax invoice shows its QR code and, once
 * cancelled, its credit note.
 *
 * @property Invoice $record
 */
final class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected string $view = 'billing::filament.invoice';

    public function getTitle(): string|Htmlable
    {
        return __('billing::invoices.model').' '.$this->record->number;
    }

    protected function getHeaderActions(): array
    {
        return [
            InvoiceActions::recordPayment()->record($this->record)->after(fn () => $this->record->refresh()),
            InvoiceActions::void()->record($this->record)->after(fn () => $this->record->refresh()),
            Action::make('payLink')->label(__('billing::payments.invoice.copy_link'))->icon(Heroicon::OutlinedLink)->color('gray')
                ->visible(fn () => $this->record->status === 'issued')
                ->modalHeading(__('billing::payments.invoice.copy_link'))->modalDescription(__('billing::payments.invoice.copy_link_hint'))
                ->modalSubmitAction(false)
                ->modalContent(fn () => view('billing::filament.pay-link', ['url' => app(PayLinks::class)->invoice($this->record, $this->record->billingAccount?->payer_id)])),
            Action::make('print')->label(__('billing::invoices.print'))->icon(Heroicon::OutlinedPrinter)->color('gray')
                ->alpineClickHandler('window.print()'),
        ];
    }

    /** @return array<string, mixed> */
    public function document(): array
    {
        $invoice = $this->record;
        $tz = app(ViewerTimezone::class)->effective();
        $d = app(InvoiceDocument::class)->build($invoice, $tz);
        $date = fn ($at) => $at?->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F Y');

        return $d + [
            'dueLine' => InvoiceResource::dueLine($invoice),
            'checkouts' => PaymentCheckout::query()->with('account')->where('invoice_id', $invoice->id)->latest()->get()->map(fn (PaymentCheckout $c) => [
                'when' => $date($c->created_at).' · '.$c->created_at->setTimezone($tz)->format('H:i'),
                'gateway' => $c->account?->label,
                'status' => $c->status,
                'amount' => $c->money()->format(),
                'ref' => $c->provider_payment_ref ?? $c->provider_ref,
                'failure' => $c->failure,
            ])->all(),
            'enrollmentUrl' => $d['student'] ? route('filament.academy.resources.enrollments.view', ['record' => $d['student']->enrollment_id]) : null,
        ];
    }
}
