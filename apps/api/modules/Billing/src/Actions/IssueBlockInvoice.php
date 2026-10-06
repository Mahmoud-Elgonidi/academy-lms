<?php

namespace Modules\Billing\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\Events\InvoiceIssued;
use Modules\Billing\Domain\TaxCalculator;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Subscription;

/**
 * Issues the invoice for one block of a subscription: the plan's sessions
 * at the agreed price, VAT per the academy's settings, the next number.
 * One invoice per block: asking again returns the one already issued.
 */
final class IssueBlockInvoice
{
    public function __construct(private readonly NextInvoiceNumber $numbers) {}

    public function handle(Subscription $subscription, int $blockNo, string $subjectAr, string $subjectEn, ?string $actorId = null): Invoice
    {
        return DB::transaction(function () use ($subscription, $blockNo, $subjectAr, $subjectEn, $actorId) {
            Subscription::query()->whereKey($subscription->id)->lockForUpdate()->first();

            $existing = Invoice::query()
                ->where('subscription_id', $subscription->id)
                ->whereIn('status', ['issued', 'paid'])
                ->whereHas('lines', fn ($q) => $q->where('kind', 'block')->whereRaw("(meta->>'block_no')::int = ?", [$blockNo]))
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $plan = $subscription->plan;
            $settings = BillingSettings::current();
            $amounts = TaxCalculator::compute($subscription->amount_minor, $settings->tax_rate_bp, $settings->prices_include_tax);
            $now = CarbonImmutable::now();

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
            ]);

            $invoice->lines()->create([
                'kind' => 'block',
                'description_ar' => __('billing::billing.lines.block', ['subject' => $subjectAr, 'plan' => $plan->name_ar, 'block' => $blockNo], 'ar'),
                'description_en' => __('billing::billing.lines.block', ['subject' => $subjectEn, 'plan' => $plan->name_en, 'block' => $blockNo], 'en'),
                'quantity' => 1,
                'unit_minor' => $subscription->amount_minor,
                'amount_minor' => $subscription->amount_minor,
                'meta' => [
                    'block_no' => $blockNo,
                    'minutes' => $plan->terms()->minutes(),
                    'sessions' => $plan->sessions_per_block,
                    'session_minutes' => $plan->session_minutes,
                    'validity_days' => $plan->validity_days,
                ],
            ]);

            DB::afterCommit(fn () => event(new InvoiceIssued($invoice->academy_id, $invoice->id, $invoice->billing_account_id, $subscription->id)));

            return $invoice;
        });
    }
}
