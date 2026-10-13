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
 * time of issue and QR code, with the credit note that cancelled it; while
 * something is left to pay, where to transfer it (the academy's words for
 * the payer's region, as they read today: not part of what was issued).
 */
final class InvoiceDocument
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly TransferDetails $transfer,
    ) {}

    /** @return array<string, mixed> */
    public function build(Invoice $invoice, string $tz): array
    {
        $invoice->loadMissing(['lines', 'payments', 'billingAccount', 'creditNote']);
        $academy = $this->tenants->require();
        $locale = app()->getLocale();
        $date = fn (?CarbonImmutable $at) => $at?->setTimezone($tz)->locale($locale)->translatedFormat('j F Y');
        $time = fn (?CarbonImmutable $at) => $at?->setTimezone($tz)->locale($locale)->translatedFormat('j F Y · H:i');
        $payer = DB::table('users')->where('id', $invoice->billingAccount->payer_id)->first(['name', 'email', 'phone_e164']);
        $courses = InvoiceCourses::ofOne($invoice->id);
        $students = collect($courses)->unique('student_id')->values();
        // One student: their name and account; a family invoice: each child, linked to their course.
        $student = $courses === [] ? null : (object) [
            'name' => InvoiceCourses::students($courses),
            'enrollment_id' => $students->count() === 1 ? $courses[0]['enrollment_id'] : null,
        ];
        // Each course's lines together (what it is, then what comes off it), in the order the courses are named.
        $order = array_flip(array_column($courses, 'subscription_id'));
        $lines = $invoice->lines->sortBy(fn ($l) => [$order[$l->subscription_id] ?? PHP_INT_MAX, $l->kind === 'discount' ? 1 : 0])->values();
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
            'courses' => $courses,
            'family' => count($courses) > 1,
            'lines' => $lines,
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
            'transfer' => $this->transfer->forInvoice($invoice),
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
