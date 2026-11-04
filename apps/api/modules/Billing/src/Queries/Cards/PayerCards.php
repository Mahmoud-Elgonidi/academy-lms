<?php

namespace Modules\Billing\Queries\Cards;

use Modules\Billing\Models\AutoCharge;
use Modules\Billing\Models\BillingAccount;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Payments\Cards\Mandate;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * A payer's saved cards and automatic renewal as they may read them (J5b):
 * brand, last four and expiry, never a card's references; per account of
 * theirs whether renewals are charged automatically and to which card; and
 * the charges coming (amount, moment). The same reading for the family's
 * API, the course balance and the signed "turn it off" page.
 */
final class PayerCards
{
    public function __construct(private readonly LocalTimePresenter $time) {}

    /** @return list<PaymentMethod> the payer's live cards, the newest first */
    public function cards(string $payerId): array
    {
        return PaymentMethod::query()->live()->with('account')->where('payer_id', $payerId)->orderByDesc('created_at')->get()->all();
    }

    /** @return array<string, mixed> */
    public function present(string $payerId): array
    {
        $accounts = BillingAccount::query()->where('payer_id', $payerId)->orderBy('created_at')->get();
        $coming = AutoCharge::query()->where('payer_id', $payerId)->whereIn('status', [AutoCharge::SCHEDULED, AutoCharge::RETRY, AutoCharge::CHARGING])
            ->orderBy('charge_at')->get();
        $numbers = Invoice::query()->whereIn('id', $coming->pluck('invoice_id'))->pluck('number', 'id');

        return [
            /** The cards kept for this payer: brand, last four and expiry only. */
            'cards' => collect($this->cards($payerId))->map(fn (PaymentMethod $m) => [
                'id' => (string) $m->id,
                /** visa, mastercard, mada, amex, … or card */
                'brand' => (string) $m->brandKey(),
                'brand_label' => (string) __('billing::cards.brands.'.$m->brandKey()),
                'last4' => (string) $m->last4,
                'exp_month' => (int) $m->exp_month,
                'exp_year' => (int) $m->exp_year,
                /** "Visa ****4242" */
                'label' => (string) $m->label(),
                /** The gateway account it is kept at (its name as payers see it). */
                'account' => (string) $m->account?->label,
                /** It expires within a month, or has expired: ask for a new one. */
                'expires_soon' => (bool) $m->expiresBefore(now()->toImmutable()->addDays(30)),
                'saved_at' => $this->time->present($m->created_at),
            ])->values()->all(),
            /** Per account of this payer (one per currency, usually one): automatic renewal and the card it uses. */
            'accounts' => $accounts->map(function (BillingAccount $a) use ($payerId) {
                $card = PaymentMethod::liveFor($payerId, $a->currency);

                return [
                    'id' => $a->id,
                    'currency' => $a->currency,
                    /** Renewals are charged to the card three days after they are told. */
                    'auto_renew' => (bool) ($a->auto_renew && $card !== null),
                    /** The card a renewal in this currency is charged to; null when none can be. */
                    'card_id' => $card?->id,
                ];
            })->values()->all(),
            /** Whether any of this payer's renewals are charged automatically. */
            'auto_renew' => (bool) $accounts->contains(fn (BillingAccount $a) => $a->auto_renew && PaymentMethod::liveFor($payerId, $a->currency) !== null),
            /** Automatic charges coming (or under way): turn automatic renewal off to stop the ones still to come. */
            'coming' => $coming->map(fn (AutoCharge $c) => [
                'invoice_id' => $c->invoice_id,
                'number' => (string) ($numbers[$c->invoice_id] ?? ''),
                'amount' => $c->money()->format(),
                'amount_minor' => $c->amount_minor,
                'currency' => $c->currency,
                /** scheduled, retry (a soft decline, tried once more) or charging (under way: it can no longer be stopped). */
                'status' => $c->status,
                'at' => $this->time->present($c->nextAt()),
            ])->values()->all(),
            /** The words ticked to save a card and renew automatically, and their version (send it back with save_card). */
            'mandate' => ['version' => Mandate::VERSION, 'text' => Mandate::text()],
        ];
    }

    /**
     * A course's automatic renewal for its payer (the balance's auto_renew).
     *
     * @return array<string, mixed>
     */
    public function forCourse(string $payerId, string $currency, ?string $waitingInvoiceId): array
    {
        $account = BillingAccount::query()->where('payer_id', $payerId)->where('currency', $currency)->first()
            ?? BillingAccount::query()->where('payer_id', $payerId)->first();
        $card = PaymentMethod::liveFor($payerId, $currency);
        $next = $waitingInvoiceId === null ? null
            : AutoCharge::query()->where('invoice_id', $waitingInvoiceId)->whereIn('status', [AutoCharge::SCHEDULED, AutoCharge::RETRY, AutoCharge::CHARGING])->first();
        $on = (bool) ($account?->auto_renew) && $card !== null;

        return [
            /** Renewals of this course are charged to the saved card. */
            'on' => (bool) $on,
            /** The card renewals in this currency are charged to: brand, last four, expiry only. */
            'card' => $card === null ? null : [
                'id' => (string) $card->id,
                'brand' => (string) $card->brandKey(),
                'last4' => (string) $card->last4,
                'exp_month' => (int) $card->exp_month,
                'exp_year' => (int) $card->exp_year,
                'label' => (string) $card->label(),
                'expires_soon' => (bool) $card->expiresBefore(now()->toImmutable()->addDays(30)),
            ],
            /** The waiting invoice's automatic charge: when, and how much. */
            'next_charge' => $next === null ? null : [
                'at' => $this->time->present($next->nextAt()),
                'amount' => (string) $next->money()->format(),
                'amount_minor' => (int) $next->amount_minor,
                /** scheduled, retry or charging */
                'status' => (string) $next->status,
            ],
            'text' => (string) match (true) {
                $on => __('billing::cards.balance.on', ['card' => $card->label()]),
                $card !== null => __('billing::cards.balance.off_with_card', ['card' => $card->label()]),
                default => __('billing::cards.balance.off'),
            },
        ];
    }
}
