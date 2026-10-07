<?php

namespace Modules\Billing\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Models\CreditNote;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Zatca\TaxDocument;
use Modules\SharedKernel\Money\Money;

/**
 * A credit note cancelling an issued tax invoice in full (ZATCA: issued
 * invoices are never deleted or changed). It carries the same seller and
 * amounts, its own gapless number, the reason, and its own QR code. Called
 * by the Action that cancels the invoice, inside its transaction.
 */
final class IssueCreditNote
{
    public function __construct(private readonly NextInvoiceNumber $numbers) {}

    public function handle(Invoice $invoice, string $reason, ?string $actorId): CreditNote
    {
        return DB::transaction(function () use ($invoice, $reason, $actorId) {
            $existing = CreditNote::query()->where('invoice_id', $invoice->id)->first();
            if ($existing !== null) {
                return $existing;
            }
            $now = CarbonImmutable::now();

            return CreditNote::query()->create([
                'number' => $this->numbers->takeCreditNote(),
                'invoice_id' => $invoice->id,
                'reason' => mb_substr(trim($reason), 0, 200),
                'currency' => $invoice->currency,
                'subtotal_minor' => $invoice->subtotal_minor,
                'tax_minor' => $invoice->tax_minor,
                'total_minor' => $invoice->total_minor,
                'tax_rate_bp' => $invoice->tax_rate_bp,
                'seller_name' => $invoice->seller_name,
                'seller_tax_number' => $invoice->seller_tax_number,
                'zatca_qr' => TaxDocument::qr($invoice->tax_document, $invoice->seller_name, $invoice->seller_tax_number, $now, $invoice->total(),
                    Money::of($invoice->tax_minor, $invoice->currency)),
                'issued_at' => $now,
                'created_by' => $actorId,
            ]);
        });
    }
}
