<?php

namespace Modules\Billing\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\Invoice;
use Modules\SharedKernel\Audit\AuditTrail;

/** Cancels an issued invoice nothing has been paid on. Its number stays used. */
final class VoidInvoice
{
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
            $invoice->save();
            app(AuditTrail::class)->record('billing.invoice_voided', 'invoice', $invoice->id, ['number' => $invoice->number, 'reason' => $invoice->void_reason]);

            return $invoice;
        });
    }
}
