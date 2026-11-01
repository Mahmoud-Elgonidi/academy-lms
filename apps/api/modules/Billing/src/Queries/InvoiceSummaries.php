<?php

namespace Modules\Billing\Queries;

use Modules\Billing\Actions\Discounts\PayerCoupon;
use Modules\Billing\Contracts\DTOs\InvoiceSummary;
use Modules\Billing\Filament\Resources\Invoices\InvoiceResource;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Payments\PayLinks;

/** An invoice as its payer reads it before paying (InvoiceSummary), for the pay page, the family's apps and a course just started. */
final class InvoiceSummaries
{
    public function __construct(private readonly PayLinks $links) {}

    public function of(Invoice $invoice, ?string $payerId): InvoiceSummary
    {
        $invoice->loadMissing('lines');
        $why = PayerCoupon::whyNot($invoice);
        $lines = [];
        foreach ($invoice->lines->sortBy(fn ($l) => [$l->subscription_id ?? '', $l->kind === 'discount' ? 1 : 0]) as $line) {
            $lines[] = ['kind' => (string) $line->kind, 'description' => $line->description(), 'quantity' => (int) $line->quantity, 'amount_minor' => (int) $line->amount_minor];
        }
        $outstanding = $invoice->status === 'issued' ? $invoice->outstanding()->minor : 0;

        return new InvoiceSummary(
            id: $invoice->id,
            number: (string) $invoice->number,
            status: InvoiceResource::statusKey($invoice),
            currency: $invoice->currency,
            lines: $lines,
            subtotalMinor: (int) $invoice->subtotal_minor,
            taxMinor: (int) $invoice->tax_minor,
            taxRateBp: (int) $invoice->tax_rate_bp,
            pricesIncludeTax: (bool) $invoice->prices_include_tax,
            totalMinor: (int) $invoice->total_minor,
            paidMinor: (int) $invoice->paid_minor,
            outstandingMinor: $outstanding,
            dueAt: $invoice->status === 'issued' ? $invoice->due_at : null,
            coupon: PayerCoupon::onInvoice($invoice),
            couponOpen: $why === null,
            couponWhyNot: $why === null || $invoice->status !== 'issued' ? null : (string) __('billing::discounts.payer.'.$why),
            payUrl: $this->links->invoice($invoice, $payerId),
            documentUrl: $this->links->document($invoice, $payerId),
        );
    }
}
