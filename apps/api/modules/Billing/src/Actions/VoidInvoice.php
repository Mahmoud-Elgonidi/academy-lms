<?php

namespace Modules\Billing\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Models\PaymentProof;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Cancels an issued invoice nothing has been paid on. Its number stays
 * used. A Saudi tax invoice (ZATCA) is cancelled by a credit note, issued
 * here with it. Not while money may be on its way: a transfer receipt
 * waiting (approve it or turn it down first, so the payer hears) or an
 * online payment under way (until it finishes or runs out).
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
            $why = match (true) {
                PaymentProof::query()->where('invoice_id', $invoice->id)->where('status', 'pending')->exists() => 'void_proof',
                PaymentCheckout::query()->where('invoice_id', $invoice->id)->where('status', 'open')->where('expires_at', '>', CarbonImmutable::now())->exists() => 'void_checkout',
                default => null,
            };
            if ($why !== null) {
                throw ValidationException::withMessages(['invoice' => __('billing::billing.errors.'.$why)]);
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
