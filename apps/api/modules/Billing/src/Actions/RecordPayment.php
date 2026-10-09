<?php

namespace Modules\Billing\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Contracts\Events\InvoicePaid;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\Subscription;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Credits\Contracts\LotSource;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Money\Money;

/**
 * Money received for an invoice: a bank transfer, InstaPay, a wallet, cash
 * (docs/architecture/03, "يدوي"), or online from a checkout the gateway
 * confirmed (SettleCheckout; one payment per checkout, which the database
 * holds to). When the invoice is paid in full, in the
 * same transaction (docs/architecture/04, rule 4): each course on it (one,
 * or a family's several) gets its block's minutes, its subscription moves
 * to that block, and its enrollments start. Scheduling then funds the
 * waiting sessions, oldest first.
 */
final class RecordPayment
{
    public function __construct(
        private readonly CreditsApi $credits,
        private readonly EnrollmentApi $enrollments,
    ) {}

    /** @param  ?string  $paymentId  the new payment's id, when the caller keeps it (an approved proof points to its payment) */
    public function handle(string $invoiceId, string $method, Money $amount, ?string $reference, CarbonImmutable $receivedAt, ?string $note, ?string $actorId, ?string $checkoutId = null, ?string $paymentId = null): Invoice
    {
        if ($checkoutId === null ? ! in_array($method, Payment::METHODS, true) : $method !== Payment::ONLINE) {
            throw ValidationException::withMessages(['method' => __('billing::billing.errors.method')]);
        }

        return DB::transaction(function () use ($invoiceId, $method, $amount, $reference, $receivedAt, $note, $actorId, $checkoutId, $paymentId) {
            $invoice = Invoice::lock($invoiceId);
            if ($invoice->status !== 'issued') {
                throw ValidationException::withMessages(['invoice' => __('billing::billing.errors.not_payable')]);
            }
            if ($amount->currency !== $invoice->currency) {
                throw ValidationException::withMessages(['amount' => __('billing::billing.errors.currency', ['currency' => $invoice->currency])]);
            }
            if ($amount->minor <= 0 || $amount->minor > $invoice->outstanding()->minor) {
                throw ValidationException::withMessages(['amount' => __('billing::billing.errors.amount', ['max' => $invoice->outstanding()->format()])]);
            }

            $invoice->payments()->create(array_filter(['id' => $paymentId]) + [
                'method' => $method,
                'amount_minor' => $amount->minor,
                'currency' => $amount->currency,
                'reference' => $reference,
                'received_at' => $receivedAt->utc(),
                'recorded_by' => $actorId,
                'note' => $note,
                'checkout_id' => $checkoutId,
            ]);

            $invoice->paid_minor += $amount->minor;
            if ($invoice->paid_minor === $invoice->total_minor) {
                $invoice->status = 'paid';
                $invoice->paid_at = $receivedAt->utc();
            }
            $invoice->save();

            if ($invoice->status === 'paid') {
                $this->fulfil($invoice, $actorId);
                DB::afterCommit(fn () => event(new InvoicePaid($invoice->academy_id, $invoice->id, $invoice->subscriptionIds())));
            }
            if ($checkoutId === null) {
                app(AuditTrail::class)->record('billing.payment_recorded', 'invoice', $invoice->id, ['number' => $invoice->number, 'amount' => $amount->format(), 'method' => $method, 'reference' => $reference]);
            }

            return $invoice;
        });
    }

    /**
     * An invoice that came to nothing (a full scholarship): paid as it is
     * issued, with no payment, and what it buys is granted at once.
     */
    public function nothingDue(Invoice $invoice, ?string $actorId): void
    {
        DB::transaction(function () use ($invoice, $actorId) {
            $invoice = Invoice::lock($invoice->id);
            if ($invoice->status !== 'issued' || $invoice->total_minor !== 0) {
                return;
            }
            $invoice->status = 'paid';
            $invoice->paid_at = CarbonImmutable::now();
            $invoice->save();
            $this->fulfil($invoice, $actorId);
            DB::afterCommit(fn () => event(new InvoicePaid($invoice->academy_id, $invoice->id, $invoice->subscriptionIds())));
        });
    }

    /** What a paid invoice delivers, to each course on it: its blocks' and extra lessons' minutes, valid from today. */
    private function fulfil(Invoice $invoice, ?string $actorId): void
    {
        $lines = $invoice->lines()->whereIn('kind', ['block', 'extra'])->whereNotNull('subscription_id')->get()->groupBy('subscription_id');
        // Locked in one order, so two family invoices paid at once cannot wait on each other.
        foreach ($lines->keys()->sort()->values() as $subscriptionId) {
            $subscription = Subscription::query()->whereKey($subscriptionId)->lockForUpdate()->first();
            if ($subscription === null) {
                continue;
            }

            foreach ($lines[$subscriptionId] as $line) {
                $this->credits->grant(
                    $subscription->credit_account_id,
                    LotSource::Purchase,
                    $invoice->id,
                    (int) $line->meta['minutes'],
                    CarbonImmutable::now()->addDays((int) $line->meta['validity_days'])->endOfDay(),
                    "invoice:{$invoice->id}:line:{$line->id}",
                    $actorId,
                    'invoice '.$invoice->number,
                );
                if ($line->kind === 'block') {
                    $subscription->block_no = max($subscription->block_no, (int) $line->meta['block_no']);
                }
            }

            $subscription->status = 'active';
            $subscription->save();
            // Every course on its balance: one, or each of a shared package's (its own course at least).
            $courses = array_map(fn ($e) => $e->id, $this->enrollments->forCreditAccount($subscription->credit_account_id));
            foreach (array_unique([$subscription->enrollment_id, ...$courses]) as $enrollmentId) {
                $this->enrollments->activate($enrollmentId, $actorId);
            }
        }
    }
}
