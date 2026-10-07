<?php

namespace Modules\Billing\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\Invoice;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Cancels an issued invoice nothing has been paid on. Its number stays
 * used. A Saudi tax invoice (ZATCA) is cancelled by a credit note, issued
 * here with it.
 */
final class VoidInvoice
{
    public function __construct(private readonly IssueCreditNote $creditNotes) {}

    public function handle(string $invoiceId, string $reason, ?string $actorId): Invoice
    {
        return DB::transaction(function () use ($invoiceId, $reason, $actorId) {
            $invoice = Invoice::lock($invoiceId);
            if ($invoice->status !== 'issued' || $invoice->paid_minor > 0) {
                throw ValidationException::withMessages(['invoice' => __('billing::billing.errors.not_voidable')]);
            }

            $invoice->status = 'void';
            $invoice->voided_at = CarbonImmutable::now();
            $invoice->void_reason = trim($reason);
            $invoice->voided_by = $actorId;
            if ($invoice->tax_document !== null) {
                $invoice->credit_note_id = $this->creditNotes->handle($invoice, $invoice->void_reason, $actorId)->id;
            }
            $invoice->save();
            app(AuditTrail::class)->record('billing.invoice_voided', 'invoice', $invoice->id, array_filter([
                'number' => $invoice->number, 'reason' => $invoice->void_reason, 'credit_note' => $invoice->creditNote?->number,
            ]));

            return $invoice;
        });
    }
}
