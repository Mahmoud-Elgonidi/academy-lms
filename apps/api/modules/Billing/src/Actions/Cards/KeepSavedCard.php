<?php

namespace Modules\Billing\Actions\Cards;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Models\BillingAccount;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Payments\Cards\Mandate;
use Modules\Billing\Payments\Cards\SavedCard;
use Modules\Billing\Queries\Collections\PayerReach;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Keeps the card a gateway kept with a paid checkout whose payer ticked
 * "save my card and renew automatically" (J5b): only from SettleCheckout,
 * with what the gateway itself answered. One live card per payer and gateway
 * account: an older one there is let go (replaced). The mandate they ticked
 * turns automatic renewal on for their accounts. Audited with the brand and
 * last four only.
 */
final class KeepSavedCard
{
    public function __construct(private readonly PayerReach $reach) {}

    public function handle(PaymentCheckout $checkout, SavedCard $card): ?PaymentMethod
    {
        $payerId = $checkout->payer_id;
        if ($payerId === null || ! $checkout->save_card || $checkout->status !== 'paid' || ! $card->complete()) {
            return null;
        }

        return DB::transaction(function () use ($checkout, $card, $payerId) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['cards:'.$payerId]);
            if (PaymentMethod::query()->where('checkout_id', $checkout->id)->exists()) {
                return null;    // kept already (the return and the webhook both settle)
            }
            $now = CarbonImmutable::now();
            PaymentMethod::query()->live()->where('payer_id', $payerId)->where('gateway_account_id', $checkout->gateway_account_id)
                ->lockForUpdate()->get()->each(fn (PaymentMethod $old) => $old->revoke('replaced'));
            $method = PaymentMethod::query()->create([
                'payer_id' => $payerId,
                'gateway_account_id' => $checkout->gateway_account_id,
                'gateway' => (string) $checkout->account()->value('gateway'),
                'tokens' => $card->tokens,
                'brand' => mb_substr(strtolower($card->brand), 0, 20),
                'last4' => $card->last4,
                'exp_month' => $card->expMonth,
                'exp_year' => $card->expYear,
                'mandate_version' => (string) $checkout->mandate_version,
                'mandate_hash' => Mandate::hash($this->reach->locale($payerId)),
                'checkout_id' => $checkout->id,
            ]);
            BillingAccount::query()->where('payer_id', $payerId)->where('auto_renew', false)
                ->update(['auto_renew' => true, 'auto_renew_changed_at' => $now]);
            app(AuditTrail::class)->record('billing.card_saved', 'payment_method', $method->id, [
                'brand' => $method->brandKey(), 'last4' => $method->last4, 'mandate_version' => $method->mandate_version,
            ], AuditActor::current());

            return $method;
        });
    }
}
