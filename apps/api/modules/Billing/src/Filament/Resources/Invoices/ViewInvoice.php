<?php

namespace Modules\Billing\Filament\Resources\Invoices;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Filament\InvoiceActions;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Payments\PayLinks;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * One invoice as the payer receives it, printable, with what has been paid
 * and the actions that apply: its pay link (to send by hand), and each go
 * at paying it online. The ZATCA QR comes later.
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
        $invoice = $this->record->load(['lines', 'payments', 'billingAccount']);
        $tz = app(ViewerTimezone::class)->effective();
        $academy = app(TenantContext::class)->require();
        $payer = DB::table('users')->where('id', $invoice->billingAccount->payer_id)->first(['name', 'email', 'phone_e164']);
        $student = $invoice->subscription_id ? DB::table('subscriptions as s')
            ->join('enrollments as e', fn ($j) => $j->on('e.academy_id', '=', 's.academy_id')->on('e.id', '=', 's.enrollment_id'))
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'e.academy_id')->on('u.id', '=', 'e.student_id'))
            ->where('s.id', $invoice->subscription_id)->first(['u.name', 'e.id as enrollment_id']) : null;
        $date = fn ($at) => $at?->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F Y');

        return [
            'invoice' => $invoice,
            'status' => InvoiceResource::statusKey($invoice),
            'academy' => app()->getLocale() === 'ar' ? $academy->nameAr : $academy->nameEn,
            'taxNumber' => BillingSettings::current()->tax_number,
            'payer' => $payer,
            'student' => $student,
            'issued' => $date($invoice->issued_at),
            'due' => $date($invoice->due_at),
            'dueLine' => InvoiceResource::dueLine($invoice),
            'payments' => $invoice->payments->map(fn ($p) => [
                'when' => $p->received_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M Y · H:i'),
                'method' => __('billing::billing.methods.'.$p->method),
                'reference' => $p->reference,
                'amount' => $p->money()->format(),
            ])->all(),
            'checkouts' => PaymentCheckout::query()->with('account')->where('invoice_id', $invoice->id)->latest()->get()->map(fn (PaymentCheckout $c) => [
                'when' => $date($c->created_at).' · '.$c->created_at->setTimezone($tz)->format('H:i'),
                'gateway' => $c->account?->label,
                'status' => $c->status,
                'amount' => $c->money()->format(),
                'ref' => $c->provider_payment_ref ?? $c->provider_ref,
                'failure' => $c->failure,
            ])->all(),
            'enrollmentUrl' => $student ? route('filament.academy.resources.enrollments.view', ['record' => $student->enrollment_id]) : null,
        ];
    }
}
