<?php

namespace Modules\Billing\Actions\Proofs;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Contracts\Events\PaymentProofDecided;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentProof;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The money did not arrive, or the receipt does not show it: the proof is
 * turned down with a reason the payer reads, and they can send another.
 */
final class RejectPaymentProof
{
    public function handle(string $proofId, ?string $reason, string $actorId): PaymentProof
    {
        $reason = trim((string) $reason);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:300']], [], trans('billing::proofs.attributes'))->validate();

        return DB::transaction(function () use ($proofId, $reason, $actorId) {
            $proof = PaymentProof::query()->whereKey($proofId)->lockForUpdate()->firstOrFail();
            if ($proof->status !== 'pending') {
                throw ValidationException::withMessages(['proof' => __('billing::proofs.errors.decided')]);
            }
            $proof->update(['status' => 'rejected', 'decided_by' => $actorId, 'decided_at' => CarbonImmutable::now(), 'decision_note' => $reason]);

            $number = Invoice::query()->whereKey($proof->invoice_id)->value('number');
            app(AuditTrail::class)->record('billing.proof_rejected', 'invoice', $proof->invoice_id, ['number' => $number, 'amount' => $proof->money()->format(), 'reason' => $reason]);
            DB::afterCommit(fn () => event(new PaymentProofDecided($proof->academy_id, $proof->id, $proof->invoice_id, false)));

            return $proof;
        });
    }
}
