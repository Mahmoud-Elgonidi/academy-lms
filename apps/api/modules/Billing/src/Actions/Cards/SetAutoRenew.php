<?php

namespace Modules\Billing\Actions\Cards;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\BillingAccount;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Support\AutoCharges;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * The payer turns automatic renewal on or off, in one tap (J5b): on only with
 * a live card on an account that is on, and never by someone signed in as
 * them (the platform team); off at any time, from their account or the
 * signed link in the "renewal coming" message, and a charge still to come is
 * cancelled (one already under way finishes). Turned on, it takes the next
 * renewal's invoice, not one already waiting (that one is paid as usual).
 * Per account of theirs, or all of them. Audited.
 */
final class SetAutoRenew
{
    public function __construct(private readonly AutoCharges $autoCharges) {}

    /** @return list<BillingAccount> the accounts it changed */
    public function handle(string $payerId, bool $on, ?string $billingAccountId = null, string $via = 'family'): array
    {
        if ($on && AuditActor::current()->onBehalfOf !== null) {
            throw ValidationException::withMessages(['on' => __('billing::cards.errors.impersonating')]);
        }

        return DB::transaction(function () use ($payerId, $on, $billingAccountId, $via) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['cards:'.$payerId]);
            $accounts = BillingAccount::query()->where('payer_id', $payerId)
                ->when($billingAccountId !== null, fn ($q) => $q->whereKey($billingAccountId))->lockForUpdate()->get();
            if ($accounts->isEmpty()) {
                throw ValidationException::withMessages(['billing_account_id' => __('billing::cards.errors.no_account')]);
            }
            if ($on && ! $accounts->contains(fn (BillingAccount $a) => PaymentMethod::liveFor($payerId, $a->currency) !== null)) {
                throw ValidationException::withMessages(['on' => __('billing::cards.errors.no_card')]);
            }
            $changed = [];
            foreach ($accounts as $account) {
                if ($account->auto_renew === $on || ($on && PaymentMethod::liveFor($payerId, $account->currency) === null)) {
                    continue;
                }
                $account->update(['auto_renew' => $on, 'auto_renew_changed_at' => CarbonImmutable::now()]);
                $changed[] = $account;
            }
            if (! $on) {
                $this->autoCharges->cancelForPayer($payerId, 'turned_off', $billingAccountId);
            }
            if ($changed !== []) {
                app(AuditTrail::class)->record($on ? 'billing.auto_renew_on' : 'billing.auto_renew_off', 'user', $payerId, [
                    'accounts' => count($changed), 'via' => $via,
                ]);
            }

            return $changed;
        });
    }
}
