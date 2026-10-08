<?php

namespace Modules\Billing\Actions\Proofs;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Contracts\Events\PaymentProofDecided;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PaymentProof;
use Modules\SharedKernel\Money\Money;

/**
 * The academy saw the money arrive: approving a proof records the payment
 * (RecordPayment, which grants the block once the invoice is paid in full
 * and keeps the audit line) in the same transaction, and the proof points
 * to it. The amount is what actually came in, which may be less than the
 * payer wrote; the rest stays owed.
 */
final class ApprovePaymentProof
{
    public function __construct(private readonly RecordPayment $payments) {}

    public function handle(string $proofId, Money $amount, string $method, ?string $reference, CarbonImmutable $receivedAt, ?string $note, string $actorId): Invoice
    {
        if (! in_array($method, Payment::METHODS, true)) {
            throw ValidationException::withMessages(['method' => __('billing::billing.errors.method')]);
        }
        if ($receivedAt->isAfter(CarbonImmutable::now()->addMinutes(5))) {
            throw ValidationException::withMessages(['received_at' => __('billing::proofs.errors.received_future')]);
        }

        return DB::transaction(function () use ($proofId, $amount, $method, $reference, $receivedAt, $note, $actorId) {
            $proof = PaymentProof::query()->whereKey($proofId)->lockForUpdate()->firstOrFail();
            if ($proof->status !== 'pending') {
                throw ValidationException::withMessages(['proof' => __('billing::proofs.errors.decided')]);
            }

            $paymentId = (string) Str::uuid7();
            $invoice = $this->payments->handle($proof->invoice_id, $method, $amount, trim((string) $reference) ?: null, $receivedAt, trim((string) $note) ?: null, $actorId, paymentId: $paymentId);

            $proof->update(['status' => 'approved', 'decided_by' => $actorId, 'decided_at' => CarbonImmutable::now(), 'payment_id' => $paymentId]);
            DB::afterCommit(fn () => event(new PaymentProofDecided($proof->academy_id, $proof->id, $proof->invoice_id, true)));

            return $invoice;
        });
    }
}
