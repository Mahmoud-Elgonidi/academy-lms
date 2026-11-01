<?php

namespace Modules\Billing\Discounts;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\IssueBlockInvoice;
use Modules\Billing\Actions\VoidInvoice;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Models\PaymentProof;
use Modules\Billing\Models\Subscription;

/**
 * A course's block invoice waiting to be paid, issued again after its
 * coupon changed (an issued invoice never changes): voided (a credit note
 * for a tax invoice) and issued anew with the same blocks (a family invoice
 * with all of them). Only while no money may be on its way: nothing paid
 * on it, no proof waiting, no online payment under way (as VoidInvoice).
 */
final class ReissueBlock
{
    public function __construct(private readonly VoidInvoice $void, private readonly IssueBlockInvoice $issue) {}

    /** The block invoice waiting to be paid, if it can be issued again; refuses when money may be on its way. */
    public function waiting(Subscription $subscription): ?Invoice
    {
        $invoice = Invoice::query()->with('lines')->forSubscription($subscription->id, 'block')->where('status', 'issued')->latest('issued_at')->first();
        if ($invoice === null) {
            return null;
        }
        if ($invoice->paid_minor > 0
            || PaymentProof::query()->where('invoice_id', $invoice->id)->where('status', 'pending')->exists()
            || PaymentCheckout::query()->where('invoice_id', $invoice->id)->where('status', 'open')->where('expires_at', '>', CarbonImmutable::now())->exists()) {
            throw ValidationException::withMessages(['coupon' => __('billing::discounts.errors.invoice_busy', ['number' => $invoice->number])]);
        }

        return $invoice;
    }

    /** Voids the waiting invoice and issues its blocks again, this course's first. */
    public function again(Subscription $subscription, Invoice $waiting, string $reason, ?string $actorId): Invoice
    {
        $blocks = $waiting->lines->where('kind', 'block')
            ->sortBy(fn ($line) => $line->subscription_id === $subscription->id ? 0 : 1)
            ->map(function ($line) {
                $course = Subscription::query()->with('plan')->findOrFail($line->subscription_id);
                [$ar, $en] = $course->subjectNames();

                return [$course, (int) $line->meta['block_no'], $ar, $en];
            })->values()->all();
        $this->void->handle($waiting->id, $reason, $actorId);

        return $this->issue->together($blocks, $actorId);
    }
}
