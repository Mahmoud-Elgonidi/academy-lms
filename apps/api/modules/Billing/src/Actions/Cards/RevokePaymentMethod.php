<?php

namespace Modules\Billing\Actions\Cards;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\BillingAccount;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Support\AutoCharges;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The payer removes their saved card in one tap (J5b), or it is let go for
 * them (its gateway account turned off, their erasure, a card the bank says
 * is no longer good). Its references at the gateway are dropped here; with
 * no live card left, automatic renewal goes off and a charge still to come
 * is cancelled (one already under way finishes). Audited with brand and last
 * four only.
 */
final class RevokePaymentMethod
{
    public function __construct(private readonly AutoCharges $autoCharges) {}

    /** @param  string  $reason  payer | gateway_off | erased | bank */
    public function handle(string $methodId, string $payerId, string $reason = 'payer'): PaymentMethod
    {
        return DB::transaction(function () use ($methodId, $payerId, $reason) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['cards:'.$payerId]);
            $method = PaymentMethod::query()->whereKey($methodId)->where('payer_id', $payerId)->lockForUpdate()->first();
            if ($method === null) {
                throw ValidationException::withMessages(['card' => __('billing::cards.errors.not_found')]);
            }
            if ($method->revoked_at !== null) {
                return $method;     // removed already: the same answer
            }
            $method->revoke($reason);
            $this->autoCharges->cancelForMethod($method->id, 'card_removed');
            if (! PaymentMethod::query()->live()->where('payer_id', $payerId)->exists()) {
                BillingAccount::query()->where('payer_id', $payerId)->where('auto_renew', true)
                    ->update(['auto_renew' => false, 'auto_renew_changed_at' => now()]);
                $this->autoCharges->cancelForPayer($payerId, 'card_removed');
            }
            app(AuditTrail::class)->record('billing.card_revoked', 'payment_method', $method->id, [
                'brand' => $method->brandKey(), 'last4' => $method->last4, 'reason' => $reason,
            ]);

            return $method;
        });
    }
}
