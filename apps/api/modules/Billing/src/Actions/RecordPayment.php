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
use Modules\SharedKernel\Money\Money;

/**
 * Money received for an invoice: a bank transfer, InstaPay, a wallet, cash
 * (docs/architecture/03, "يدوي"). When the invoice is paid in full, in the
 * same transaction (docs/architecture/04, rule 4): the block's minutes are
 * granted, the subscription moves to that block, and the enrollment starts.
 * Scheduling then funds the waiting sessions, oldest first.
 */
final class RecordPayment
{
    public function __construct(
        private readonly CreditsApi $credits,
        private readonly EnrollmentApi $enrollments,
    ) {}

    public function handle(string $invoiceId, string $method, Money $amount, ?string $reference, CarbonImmutable $receivedAt, ?string $note, ?string $actorId): Invoice
    {
        if (! in_array($method, Payment::METHODS, true)) {
            throw ValidationException::withMessages(['method' => __('billing::billing.errors.method')]);
        }

        return DB::transaction(function () use ($invoiceId, $method, $amount, $reference, $receivedAt, $note, $actorId) {
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

            $invoice->payments()->create([
                'method' => $method,
                'amount_minor' => $amount->minor,
                'currency' => $amount->currency,
                'reference' => $reference,
                'received_at' => $receivedAt->utc(),
                'recorded_by' => $actorId,
                'note' => $note,
            ]);

            $invoice->paid_minor += $amount->minor;
            if ($invoice->paid_minor === $invoice->total_minor) {
                $invoice->status = 'paid';
                $invoice->paid_at = $receivedAt->utc();
            }
            $invoice->save();

            if ($invoice->status === 'paid') {
                $this->fulfil($invoice, $actorId);
                DB::afterCommit(fn () => event(new InvoicePaid($invoice->academy_id, $invoice->id, $invoice->subscription_id)));
            }

            return $invoice;
        });
    }

    /** What a paid invoice delivers: each block's minutes, valid from today. */
    private function fulfil(Invoice $invoice, ?string $actorId): void
    {
        $subscription = $invoice->subscription_id ? Subscription::query()->whereKey($invoice->subscription_id)->lockForUpdate()->first() : null;
        if ($subscription === null) {
            return;
        }

        foreach ($invoice->lines()->where('kind', 'block')->get() as $line) {
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
            $subscription->block_no = max($subscription->block_no, (int) $line->meta['block_no']);
        }

        $subscription->status = 'active';
        $subscription->save();
        $this->enrollments->activate($subscription->enrollment_id, $actorId);
    }
}
