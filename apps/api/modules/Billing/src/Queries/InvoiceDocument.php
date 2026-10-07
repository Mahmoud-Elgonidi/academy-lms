<?php

namespace Modules\Billing\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Models\CreditNote;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Zatca\QrSvg;
use Modules\Billing\Zatca\TaxDocument;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * An invoice as a document, the same for the back office and for the
 * payer: the seller as it was when issued, the payer and the student, the
 * lines and totals, what was paid, and for a Saudi tax invoice its title,
 * time of issue and QR code, with the credit note that cancelled it.
 */
final class InvoiceDocument
{
    public function __construct(private readonly TenantContext $tenants) {}

    /** @return array<string, mixed> */
    public function build(Invoice $invoice, string $tz): array
    {
        $invoice->loadMissing(['lines', 'payments', 'billingAccount', 'creditNote']);
        $academy = $this->tenants->require();
        $locale = app()->getLocale();
        $date = fn (?CarbonImmutable $at) => $at?->setTimezone($tz)->locale($locale)->translatedFormat('j F Y');
        $time = fn (?CarbonImmutable $at) => $at?->setTimezone($tz)->locale($locale)->translatedFormat('j F Y · H:i');
        $payer = DB::table('users')->where('id', $invoice->billingAccount->payer_id)->first(['name', 'email', 'phone_e164']);
        $student = $invoice->subscription_id ? DB::table('subscriptions as s')
            ->join('enrollments as e', fn ($j) => $j->on('e.academy_id', '=', 's.academy_id')->on('e.id', '=', 's.enrollment_id'))
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'e.academy_id')->on('u.id', '=', 'e.student_id'))
            ->where('s.id', $invoice->subscription_id)->first(['u.name', 'e.id as enrollment_id']) : null;
        $simplified = $invoice->tax_document === TaxDocument::SIMPLIFIED;
        $note = $invoice->creditNote;

        return [
            'invoice' => $invoice,
            'status' => $invoice->status === 'issued' && $invoice->isOverdue() ? 'overdue' : ($invoice->status === 'issued' && $invoice->paid_minor > 0 ? 'partly_paid' : $invoice->status),
            'academy' => $locale === 'ar' ? $academy->nameAr : $academy->nameEn,
            'seller' => $invoice->seller_name,
            'taxNumber' => $invoice->seller_tax_number,
            'simplified' => $simplified,
            'title' => $simplified ? __('billing::invoices.doc.simplified') : ($invoice->tax_rate_bp > 0 ? __('billing::invoices.doc.tax_invoice') : __('billing::invoices.model')),
            'payer' => $payer,
            'student' => $student,
            'issued' => $simplified ? $time($invoice->issued_at) : $date($invoice->issued_at),
            'due' => $date($invoice->due_at),
            'payments' => $invoice->payments->map(fn ($p) => [
                'when' => $time($p->received_at),
                'method' => __('billing::billing.methods.'.$p->method),
                'reference' => $p->reference,
                'amount' => $p->money()->format(),
            ])->all(),
            'qr' => $invoice->zatca_qr ? QrSvg::render($invoice->zatca_qr) : null,
            'creditNote' => $note === null ? null : $this->creditNote($note, $invoice, $time),
        ];
    }

    /** @return array<string, mixed> */
    private function creditNote(CreditNote $note, Invoice $invoice, \Closure $time): array
    {
        $money = fn (int $minor) => Money::of($minor, $note->currency)->format();

        return [
            'number' => $note->number,
            'issued' => $time($note->issued_at),
            'reason' => $note->reason,
            'invoice' => $invoice->number,
            'subtotal' => $money($note->subtotal_minor),
            'tax' => $money($note->tax_minor),
            'total' => $money($note->total_minor),
            'rate' => rtrim(rtrim(number_format($note->tax_rate_bp / 100, 2), '0'), '.'),
            'qr' => $note->zatca_qr ? QrSvg::render($note->zatca_qr) : null,
        ];
    }
}
