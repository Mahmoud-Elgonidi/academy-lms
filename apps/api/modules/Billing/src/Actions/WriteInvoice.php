<?php

namespace Modules\Billing\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Billing\Contracts\Events\InvoiceIssued;
use Modules\Billing\Domain\TaxCalculator;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Zatca\TaxDocument;
use Modules\SharedKernel\Money\Money;

/**
 * Issues a subscription's invoice from its lines (a block, extra lessons,
 * what comes off them): VAT per the academy's settings on what is left,
 * the next number, the seller as it is now. An invoice that comes to
 * nothing (a full scholarship) is settled at once, so what it buys is
 * granted as if it had been paid.
 */
final class WriteInvoice
{
    public function __construct(
        private readonly NextInvoiceNumber $numbers,
        private readonly TaxDocument $tax,
        private readonly RecordPayment $payments,
    ) {}

    /** @param  list<array{kind: string, description_ar: string, description_en: string, quantity: int, unit_minor: int, amount_minor: int, meta: array<string, mixed>}>  $lines */
    public function handle(Subscription $subscription, array $lines, ?string $actorId): Invoice
    {
        $sum = array_sum(array_column($lines, 'amount_minor'));
        if ($lines === [] || $sum < 0) {
            throw new InvalidArgumentException('An invoice has lines and does not come to less than nothing.');
        }

        return DB::transaction(function () use ($subscription, $lines, $sum, $actorId) {
            $settings = BillingSettings::current();
            $amounts = TaxCalculator::compute($sum, $settings->tax_rate_bp, $settings->prices_include_tax);
            $now = CarbonImmutable::now();
            $stamp = $this->tax->stamp($settings);

            $invoice = Invoice::query()->create([
                'number' => $this->numbers->take($settings->invoice_prefix),
                'billing_account_id' => $subscription->billing_account_id,
                'subscription_id' => $subscription->id,
                'status' => 'issued',
                'currency' => $subscription->currency,
                'subtotal_minor' => $amounts['subtotal'],
                'tax_minor' => $amounts['tax'],
                'total_minor' => $amounts['total'],
                'tax_rate_bp' => $settings->tax_rate_bp,
                'prices_include_tax' => $settings->prices_include_tax,
                'issued_at' => $now,
                'due_at' => $now->addDays($settings->due_days),
                'created_by' => $actorId,
                'zatca_qr' => TaxDocument::qr($stamp['tax_document'], $stamp['seller_name'], $stamp['seller_tax_number'], $now,
                    Money::of($amounts['total'], $subscription->currency), Money::of($amounts['tax'], $subscription->currency)),
            ] + $stamp);

            foreach ($lines as $line) {
                $invoice->lines()->create($line);
            }

            DB::afterCommit(fn () => event(new InvoiceIssued($invoice->academy_id, $invoice->id, $invoice->billing_account_id, $subscription->id)));

            if ($invoice->total_minor === 0) {
                $this->payments->nothingDue($invoice, $actorId);
            }

            return $invoice->refresh();
        });
    }
}
