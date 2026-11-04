<?php

namespace Modules\Billing\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\Cards\RevokePaymentMethod;
use Modules\Billing\Models\AutoCharge;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Payments\Cards\CardFailure;
use Modules\Billing\Queries\Collections\PayerReach;
use Modules\Billing\Queries\InvoiceCourses;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * How an automatic charge ends (docs/architecture/02, section 27, J5b), in one
 * place whoever learns it (the charge's own answer, a webhook, the sweeper):
 * paid (billing.charge_succeeded), a soft decline waits a day for its one
 * retry (billing.charge_retry), anything else fails (billing.charge_failed,
 * with the pay link and the reason in our words), a card the bank says is no
 * longer good is let go. And cancelling the charges still to come.
 */
final class AutoCharges
{
    /** A soft decline is tried once more this many hours later. */
    public const RETRY_HOURS = 24;

    /** At most this many tries per invoice (the first, and one retry). */
    public const MAX_ATTEMPTS = 2;

    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly PayerReach $reach,
    ) {}

    /** A charge on a saved card ended (paid, failed, expired unconfirmed, or money for an invoice paid another way). */
    public function checkoutEnded(PaymentCheckout $checkout): void
    {
        $charge = AutoCharge::query()->where('checkout_id', $checkout->id)->first();
        if ($charge === null || $checkout->status === 'open') {
            return;
        }
        match ($checkout->status) {
            'paid' => $this->paid($charge),
            // Money came for an invoice already paid another way: the office refunds it (the checkout says refund_due).
            'refund_due' => $this->finish($charge, AutoCharge::CANCELLED, ['cancel_reason' => 'paid']),
            'failed' => $this->failed($charge, CardFailure::tryFrom((string) $checkout->failure) ?? CardFailure::Declined),
            default => $this->failed($charge, CardFailure::Unconfirmed),
        };
    }

    public function paid(AutoCharge $charge): void
    {
        if (! $this->finish($charge, AutoCharge::PAID, ['failure' => null, 'next_attempt_at' => null])) {
            return;
        }
        $invoice = Invoice::query()->find($charge->invoice_id);
        $method = PaymentMethod::query()->find($charge->payment_method_id);
        $this->notifications->send(new Message('billing.charge_succeeded', $this->reach->recipients($charge->payer_id), [
            'number' => (string) $invoice?->number,
            'student' => InvoiceCourses::students(InvoiceCourses::ofOne($charge->invoice_id)),
            'amount' => $charge->money(),
            'card' => $method?->label() ?? '',
        ], ['screen' => 'invoice', 'id' => $charge->invoice_id], "auto-charge-paid:{$charge->id}"));
    }

    /** A try did not go through: once more a day later for a soft decline on the first try, else the pay link. */
    public function failed(AutoCharge $charge, CardFailure $why): void
    {
        $retry = $why->retries() && $charge->attempts < self::MAX_ATTEMPTS;
        $at = CarbonImmutable::now()->addHours(self::RETRY_HOURS);
        $done = $retry
            ? $this->finish($charge, AutoCharge::RETRY, ['failure' => $why->value, 'next_attempt_at' => $at], [AutoCharge::CHARGING])
            : $this->finish($charge, AutoCharge::FAILED, ['failure' => $why->value, 'next_attempt_at' => null], [AutoCharge::CHARGING]);
        if (! $done) {
            return;
        }
        $method = PaymentMethod::query()->find($charge->payment_method_id);
        if ($why->endsCard() && $method !== null && $method->revoked_at === null) {
            app(RevokePaymentMethod::class)->handle($method->id, $method->payer_id, 'bank');
        }
        $invoice = Invoice::query()->find($charge->invoice_id);
        $this->notifications->send(new Message($retry ? 'billing.charge_retry' : 'billing.charge_failed', $this->reach->recipients($charge->payer_id), array_filter([
            'number' => (string) $invoice?->number,
            'student' => InvoiceCourses::students(InvoiceCourses::ofOne($charge->invoice_id)),
            'amount' => $charge->money(),
            'card' => $method?->label() ?? '',
            'reason' => new Translatable('billing::cards.failures.'.$why->value),
            'retry_at' => $retry ? $at : null,
        ], fn ($v) => $v !== null), ['screen' => 'invoice', 'id' => $charge->invoice_id], "auto-charge-failed:{$charge->id}:{$charge->attempts}"));
    }

    /** Cancels the charges still to come of this card (the card was removed, or let go). */
    public function cancelForMethod(string $methodId, string $reason): int
    {
        $count = 0;
        foreach (AutoCharge::query()->where('payment_method_id', $methodId)->whereIn('status', [AutoCharge::SCHEDULED, AutoCharge::RETRY])->get() as $charge) {
            $count += $this->cancel($charge, $reason) ? 1 : 0;
        }

        return $count;
    }

    /** Cancels a payer's charges still to come (automatic renewal turned off), on one account of theirs or all. */
    public function cancelForPayer(string $payerId, string $reason, ?string $billingAccountId = null): int
    {
        $count = 0;
        $charges = AutoCharge::query()->where('payer_id', $payerId)->whereIn('status', [AutoCharge::SCHEDULED, AutoCharge::RETRY])
            ->when($billingAccountId !== null, fn ($q) => $q->whereIn('invoice_id', Invoice::query()->where('billing_account_id', $billingAccountId)->select('id')))
            ->get();
        foreach ($charges as $charge) {
            $count += $this->cancel($charge, $reason) ? 1 : 0;
        }

        return $count;
    }

    /** Cancels one charge still to come; false when it was no longer waiting (under way, or over). */
    public function cancel(AutoCharge $charge, string $reason): bool
    {
        $done = $this->finish($charge, AutoCharge::CANCELLED, ['cancel_reason' => mb_substr($reason, 0, 20), 'next_attempt_at' => null], [AutoCharge::SCHEDULED, AutoCharge::RETRY]);
        if ($done && in_array($reason, ['turned_off', 'card_removed'], true)) {
            app(AuditTrail::class)->record('billing.auto_charge_cancelled', 'invoice', $charge->invoice_id, ['reason' => $reason], AuditActor::current());
        }

        return $done;
    }

    /**
     * Moves a charge on from one of these states, once (under its row's lock).
     *
     * @param  array<string, mixed>  $with
     * @param  list<string>  $from
     */
    private function finish(AutoCharge $charge, string $status, array $with = [], array $from = [AutoCharge::CHARGING]): bool
    {
        return DB::transaction(function () use ($charge, $status, $with, $from) {
            $locked = AutoCharge::lock($charge->id);
            if (! in_array($locked->status, $from, true)) {
                return false;
            }
            $locked->update(['status' => $status] + $with);
            $charge->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }
}
