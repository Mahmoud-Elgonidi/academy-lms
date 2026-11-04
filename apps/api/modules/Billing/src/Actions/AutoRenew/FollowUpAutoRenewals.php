<?php

namespace Modules\Billing\Actions\AutoRenew;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\Payments\SettleCheckout;
use Modules\Billing\Contracts\UnpaidLessons;
use Modules\Billing\Domain\Cards\ChargeMoment;
use Modules\Billing\Domain\Collections\CollectionRules;
use Modules\Billing\Jobs\ChargeRenewal;
use Modules\Billing\Models\AutoCharge;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Payments\GatewayManager;
use Modules\Billing\Payments\PayLinks;
use Modules\Billing\Queries\Collections\PayerReach;
use Modules\Billing\Queries\InvoiceCourses;
use Modules\Communication\Contracts\DTOs\Day;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Throwable;

/**
 * Every hour, inside billing:follow-up (docs/architecture/02, section 27,
 * J5b): automatic renewal's steps, each once.
 *
 * - A renewal invoice of a payer with automatic renewal on (issued after they
 *   turned it on; a course's next block, never extra lessons or a first
 *   block; nothing paid, no receipt waiting, a course of it on) gets its
 *   automatic charge: "renewal coming" (billing.renewal_coming: the amount,
 *   the moment on their clock, the card ****1234 and a one-tap link to turn
 *   it off), from nine on their clock, with its moment (Domain\Cards\ChargeMoment).
 *   No live card for its currency, or a card that expires before the moment:
 *   skipped, and they hear why with the pay link (billing.auto_charge_skipped).
 * - At its moment (or its retry's), the charge goes to the queue (ChargeRenewal).
 * - A charge whose answer never came is asked of the gateway again (never made again).
 * - A live card used for renewals that expires within CARD_NOTICE_DAYS: the
 *   payer hears it once (billing.card_expiring), before any charge fails.
 */
final class FollowUpAutoRenewals
{
    /** A card expiring this soon is told once. */
    public const CARD_NOTICE_DAYS = 30;

    /** A renewal invoice older than this when automatic renewal meets it is left to the timeline. */
    public const FRESH_DAYS = 14;

    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly PayerReach $reach,
        private readonly UnpaidLessons $lessons,
        private readonly GatewayManager $gateways,
        private readonly PayLinks $links,
        private readonly SettleCheckout $settle,
    ) {}

    /** @return int how many messages went out and charges were sent on */
    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        return $this->notices($now) + $this->inquire($now) + $this->due($now) + $this->expiringCards($now);
    }

    /**
     * The courses of an invoice that are on now (waiting for their first payment, or running).
     *
     * @return list<string> enrollment ids
     */
    public static function coursesOn(string $invoiceId): array
    {
        return DB::table('invoice_lines as l')
            ->join('subscriptions as s', fn ($j) => $j->on('s.academy_id', '=', 'l.academy_id')->on('s.id', '=', 'l.subscription_id'))
            ->join('enrollments as e', fn ($j) => $j->on('e.academy_id', '=', 's.academy_id')->on('e.credit_account_id', '=', 's.credit_account_id'))
            ->where('l.invoice_id', $invoiceId)->whereIn('e.status', ['pending_payment', 'active'])
            ->orderBy('e.created_at')->pluck('e.id')->map(fn ($id) => (string) $id)->unique()->values()->all();
    }

    /** The payer's one-tap page to turn automatic renewal off (signed, no sign-in). */
    public function offLink(string $payerId): string
    {
        return $this->links->autoRenew($payerId);
    }

    /** Skips a charge that was never going to be made, and tells the payer why, with the pay link. */
    public function skip(AutoCharge $charge, string $reason): void
    {
        $done = DB::transaction(function () use ($charge, $reason) {
            $locked = AutoCharge::lock($charge->id);
            if (! in_array($locked->status, [AutoCharge::SCHEDULED, AutoCharge::RETRY], true)) {
                return false;
            }
            $locked->update(['status' => AutoCharge::SKIPPED, 'failure' => $reason, 'next_attempt_at' => null]);

            return true;
        });
        if ($done) {
            $this->tellSkipped($charge->refresh(), $reason);
        }
    }

    private function notices(CarbonImmutable $now): int
    {
        $invoices = Invoice::query()->with(['lines', 'billingAccount'])
            ->where('status', 'issued')->where('paid_minor', 0)->where('total_minor', '>', 0)
            ->where('issued_at', '>', $now->subDays(self::FRESH_DAYS))
            ->whereHas('billingAccount', fn ($q) => $q->where('auto_renew', true))
            ->whereNotExists(fn ($q) => $q->from('auto_charges as a')->whereColumn('a.academy_id', 'invoices.academy_id')->whereColumn('a.invoice_id', 'invoices.id'))
            ->whereNotExists(fn ($q) => $q->from('manual_payment_proofs as p')->whereColumn('p.academy_id', 'invoices.academy_id')
                ->whereColumn('p.invoice_id', 'invoices.id')->where('p.status', 'pending'))
            ->orderBy('issued_at')->get();

        $count = 0;
        foreach ($invoices as $invoice) {
            $account = $invoice->billingAccount;
            $payerId = $account->payer_id;
            // Turned on after this invoice came out: it is paid as usual; the next renewal is charged.
            if ($account->auto_renew_changed_at !== null && $account->auto_renew_changed_at->greaterThan($invoice->issued_at)) {
                continue;
            }
            if (! self::isRenewal($invoice) || $now->setTimezone($this->reach->timezone($payerId))->hour < CollectionRules::FROM_HOUR) {
                continue;
            }
            $courses = self::coursesOn($invoice->id);
            if ($courses === []) {
                continue;
            }
            $count += $this->plan($invoice, $payerId, $courses, $now);
        }

        return $count;
    }

    /** A course's next block (not its first, not extra lessons): what automatic renewal pays. */
    public static function isRenewal(Invoice $invoice): bool
    {
        $blocks = $invoice->lines->where('kind', 'block');

        return $blocks->isNotEmpty() && ! $invoice->lines->contains('kind', 'extra')
            && $blocks->every(fn ($line) => (int) ($line->meta['block_no'] ?? 0) >= 2);
    }

    /** @param  list<string>  $courses */
    private function plan(Invoice $invoice, string $payerId, array $courses, CarbonImmutable $now): int
    {
        $method = $this->usableCard($payerId, $invoice->currency);
        $timezone = $this->reach->timezone($payerId);
        $at = ChargeMoment::of($now, $timezone, $invoice->due_at, $this->lessons->firstAtRisk($courses, $now)?->cancelsAt);
        [$status, $reason] = match (true) {
            $method === null && PaymentMethod::query()->live()->where('payer_id', $payerId)->exists() => [AutoCharge::SKIPPED, 'currency'],
            $method === null => [AutoCharge::SKIPPED, 'no_card'],
            $method->expiresBefore($at) => [AutoCharge::SKIPPED, 'card_expires'],
            default => [AutoCharge::SCHEDULED, null],
        };

        try {
            $charge = DB::transaction(fn () => AutoCharge::query()->create([
                'invoice_id' => $invoice->id, 'payer_id' => $payerId, 'payment_method_id' => $method?->id,
                'amount_minor' => $invoice->outstanding()->minor, 'currency' => $invoice->currency,
                'status' => $status, 'failure' => $reason,
                'noticed_at' => $status === AutoCharge::SCHEDULED ? $now : null,
                'charge_at' => $status === AutoCharge::SCHEDULED ? $at : null,
            ]));
        } catch (UniqueConstraintViolationException) {
            return 0;   // another run planned it
        }

        if ($status === AutoCharge::SKIPPED) {
            // No card at all: automatic renewal is off in all but name (its card was let go); nothing to say.
            return $reason === 'no_card' ? 0 : $this->tellSkipped($charge, $reason);
        }
        $this->notifications->send(new Message('billing.renewal_coming', $this->reach->recipients($payerId), [
            'number' => (string) $invoice->number,
            'student' => InvoiceCourses::students(InvoiceCourses::ofOne($invoice->id)),
            'amount' => $charge->money(),
            'on' => $at,
            'card' => $method->label(),
            'off' => $this->offLink($payerId),
        ], ['screen' => 'invoice', 'id' => $invoice->id], "renewal-coming:{$invoice->id}"));

        return 1;
    }

    private function tellSkipped(AutoCharge $charge, string $reason): int
    {
        $invoice = Invoice::query()->find($charge->invoice_id);
        $method = $charge->payment_method_id !== null ? PaymentMethod::query()->find($charge->payment_method_id) : PaymentMethod::query()->where('payer_id', $charge->payer_id)->latest()->first();
        $this->notifications->send(new Message('billing.auto_charge_skipped', $this->reach->recipients($charge->payer_id), [
            'number' => (string) $invoice?->number,
            'student' => InvoiceCourses::students(InvoiceCourses::ofOne($charge->invoice_id)),
            'amount' => $charge->money(),
            'card' => $method?->label() ?? '',
            'reason' => new Translatable('billing::cards.skipped.'.$reason),
        ], ['screen' => 'invoice', 'id' => $charge->invoice_id], "auto-charge-skipped:{$charge->id}"));

        return 1;
    }

    /** The payer's live card that can be charged in this currency here (a gateway that keeps cards, on, offered). */
    private function usableCard(string $payerId, string $currency): ?PaymentMethod
    {
        $method = PaymentMethod::liveFor($payerId, $currency);
        try {
            return $method !== null && $this->gateways->for($method->account)->savesCards() ? $method : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Charges whose answer never came (or is pending at the gateway): asked again, never made again. */
    private function inquire(CarbonImmutable $now): int
    {
        $count = 0;
        $open = PaymentCheckout::query()->where('kind', 'saved')->where('status', 'open')
            ->where('created_at', '<', $now->subMinutes(30))->orderBy('created_at')->limit(100)->get();
        foreach ($open as $checkout) {
            try {
                $count += $this->settle->handle($checkout)->status !== 'open' ? 1 : 0;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $count;
    }

    /** Charges whose moment (or retry's) has come go to the queue. */
    private function due(CarbonImmutable $now): int
    {
        $due = AutoCharge::query()
            ->where(fn ($q) => $q->where(fn ($s) => $s->where('status', AutoCharge::SCHEDULED)->where('charge_at', '<=', $now))
                ->orWhere(fn ($r) => $r->where('status', AutoCharge::RETRY)->where('next_attempt_at', '<=', $now)))
            ->orderBy('charge_at')->limit(200)->pluck('id');
        foreach ($due as $id) {
            ChargeRenewal::dispatch((string) $id);
        }

        return $due->count();
    }

    /** A live card that automatic renewal uses, expiring within a month: told once, before a charge could fail on it. */
    private function expiringCards(CarbonImmutable $now): int
    {
        $count = 0;
        $cards = PaymentMethod::query()->live()->whereNull('expiry_noticed_at')
            ->whereExists(fn ($q) => $q->from('billing_accounts as b')->whereColumn('b.academy_id', 'payment_methods.academy_id')
                ->whereColumn('b.payer_id', 'payment_methods.payer_id')->where('b.auto_renew', true))
            ->get()->filter(fn (PaymentMethod $m) => $m->expiresBefore($now->addDays(self::CARD_NOTICE_DAYS)));
        foreach ($cards as $method) {
            if ($now->setTimezone($this->reach->timezone($method->payer_id))->hour < CollectionRules::FROM_HOUR) {
                continue;
            }
            $claimed = PaymentMethod::query()->whereKey($method->id)->whereNull('expiry_noticed_at')->update(['expiry_noticed_at' => $now]) === 1;
            if (! $claimed) {
                continue;
            }
            $this->notifications->send(new Message('billing.card_expiring', $this->reach->recipients($method->payer_id), [
                'card' => $method->label(),
                'on' => new Day($method->expiresAt()->toDateString()),
            ], ['screen' => 'cards', 'id' => $method->payer_id], "card-expiring:{$method->id}"));
            $count++;
        }

        return $count;
    }
}
