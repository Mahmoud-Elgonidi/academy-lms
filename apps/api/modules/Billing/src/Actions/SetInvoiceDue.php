<?php

namespace Modules\Billing\Actions;

use Carbon\CarbonImmutable;
use Modules\Billing\Models\Invoice;

/**
 * When an invoice waiting to be paid is due, set by whoever knows the moment
 * that matters to the family: a course the family started on its own is due
 * by its pay-by moment (its first lesson still payable, less rule 16's hours:
 * paid later, that lesson is cancelled unpaid), never the academy's general
 * number of days, so the invoice, the start's answer and the pay page say one
 * moment (docs/architecture/02, section 27, J3); an invoice issued again
 * after its coupon changed keeps the one it had. A trial offer's end is not
 * this: once on the invoice the discount stays until it is paid or voided.
 * Only an issued invoice with nothing paid on it; the due date is not one of
 * an issued invoice's frozen parts (Invoice::FROZEN).
 *
 * @return bool whether it changed
 */
final class SetInvoiceDue
{
    public function handle(string $invoiceId, CarbonImmutable $dueAt): bool
    {
        $invoice = Invoice::lock($invoiceId);
        if ($invoice->status !== 'issued' || $invoice->paid_minor > 0 || $invoice->due_at?->equalTo($dueAt)) {
            return false;
        }
        $invoice->forceFill(['due_at' => $dueAt->utc()])->save();

        return true;
    }
}
