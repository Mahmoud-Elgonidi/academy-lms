<?php

namespace Modules\Billing\Actions\Collections;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Domain\Collections\CollectionRules;
use Modules\Billing\Models\CollectionStep;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentProof;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Queries\Collections\PayerReach;
use Modules\Billing\Queries\InvoiceCourses;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The office sends an invoice's payment link to its payer by hand (the live
 * board's "send payment link" on a lesson waiting to be paid, the invoice's
 * page; docs/architecture/02, section 27, J5a): to the payer's channels
 * (PayerReach), with their own signed pay page, at most once an hour per
 * invoice (CollectionRules::PAY_LINK_MINUTES, under the invoice's lock).
 * Refused, in the office's words, for an invoice paid or voided, with
 * nothing left, or a transfer receipt waiting to be checked. Audited.
 */
final class SendPayLink
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly PayerReach $reach,
        private readonly AuditTrail $audit,
    ) {}

    /** The invoice of this course waiting to be paid that a link is for (its block first, then extra lessons, the oldest due); null when none. */
    public static function invoiceFor(string $enrollmentId): ?Invoice
    {
        $subscription = Subscription::ofEnrollment($enrollmentId);

        return $subscription === null ? null : Invoice::query()->forSubscription($subscription->id)->where('status', 'issued')
            ->whereColumn('paid_minor', '<', 'total_minor')
            ->orderByRaw("case when exists (select 1 from invoice_lines l where l.academy_id = invoices.academy_id and l.invoice_id = invoices.id and l.kind = 'block') then 0 else 1 end")
            ->orderBy('due_at')->first();
    }

    /** @return int how many people it went to */
    public function handle(string $invoiceId, string $actorId, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        return DB::transaction(function () use ($invoiceId, $actorId, $now) {
            $invoice = Invoice::lock($invoiceId);
            $invoice->load('billingAccount');
            if ($invoice->status !== 'issued' || $invoice->outstanding()->minor <= 0) {
                throw ValidationException::withMessages(['invoice' => __('billing::collections.errors.paid')]);
            }
            if (PaymentProof::query()->where('invoice_id', $invoice->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['invoice' => __('billing::collections.errors.proof_waiting')]);
            }
            $last = CollectionStep::query()->where('invoice_id', $invoice->id)->where('step', CollectionRules::PAY_LINK)->max('sent_at');
            if ($last !== null && CarbonImmutable::parse($last)->addMinutes(CollectionRules::PAY_LINK_MINUTES)->greaterThan($now)) {
                throw ValidationException::withMessages(['invoice' => trans_choice('billing::collections.errors.just_sent',
                    max(1, (int) ceil($now->diffInMinutes(CarbonImmutable::parse($last)->addMinutes(CollectionRules::PAY_LINK_MINUTES)))))]);
            }
            $payerId = (string) $invoice->billingAccount?->payer_id;
            $key = "{$invoice->id}:pay_link:".$now->format('YmdHi');
            CollectionStep::claim($key, ['invoice_id' => $invoice->id, 'payer_id' => $payerId, 'step' => CollectionRules::PAY_LINK, 'sent_by' => $actorId]);

            $heard = $this->notifications->send(new Message('billing.pay_link', $this->reach->recipients($payerId), [
                'number' => (string) $invoice->number,
                'student' => InvoiceCourses::students(InvoiceCourses::ofOne($invoice->id)),
                'left' => $invoice->outstanding(),
            ], ['screen' => 'invoice', 'id' => $invoice->id], 'pay-link:'.$key));
            CollectionStep::query()->where('key', $key)->update(['recipients' => $heard]);
            $this->audit->record('billing.pay_link_sent', 'invoice', $invoice->id, ['number' => $invoice->number]);

            return $heard;
        });
    }
}
