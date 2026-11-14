<?php

namespace Modules\Platform\Actions\Recharging;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Domain\Metering\Ledger;
use Modules\Platform\Metering\Meters;
use Modules\Platform\Models\Recharging\TopUpPurchase;
use Modules\Platform\Payments\CardDecline;
use Modules\Platform\Payments\StripeClient;
use Modules\Platform\Payments\StripePayment;
use Modules\Platform\Plans\Requests\PlanRequests;
use Modules\Platform\Recharging\Cards;
use Modules\Platform\Recharging\InAcademy;
use Modules\Platform\Recharging\Receipts;
use Modules\SharedKernel\Plans\Events\RechargeSucceeded;

/**
 * The one way an online or automatic purchase's money counts (docs/
 * architecture/02, section 11, "الشحن"): Stripe is asked about it (the
 * Checkout Session, or the saved card's PaymentIntent), and only when Stripe
 * says it was paid, for the price asked, does the purchase become paid, its
 * lot (12 months) go into the usage ledger and its receipt get its number,
 * all in one transaction under the purchase's lock; the card is kept when the
 * academy ticked it. Once: a purchase already answered is left alone, and the
 * database holds one lot and one receipt a purchase. Called by the webhook,
 * the way back from Stripe, the automatic charge and the hourly sweep.
 * Expired and declined purchases are closed; a declined automatic one goes to
 * ChargeAutoTopUp's rules. The staff who see the plan hear of what was added.
 */
final class SettleTopUpPayment
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly Meters $meters,
        private readonly Receipts $receipts,
        private readonly Cards $cards,
        private readonly PlatformAudit $audit,
        private readonly InAcademy $academies,
    ) {}

    public function handle(TopUpPurchase|string $purchase): TopUpPurchase
    {
        $purchase = $purchase instanceof TopUpPurchase ? $purchase : TopUpPurchase::query()->findOrFail($purchase);
        if (! $purchase->isOpen() || $purchase->method === TopUpPurchase::TRANSFER || $purchase->provider_ref === null) {
            return $purchase;
        }
        $answer = $purchase->method === TopUpPurchase::CARD
            ? $this->stripe->checkout($purchase->provider_ref)
            : $this->stripe->intent($purchase->provider_ref);

        $receipt = null;
        $declined = null;
        $settled = DB::connection('pgsql_platform')->transaction(function () use ($purchase, $answer, &$receipt, &$declined): TopUpPurchase {
            $purchase = TopUpPurchase::query()->lockForUpdate()->findOrFail($purchase->id);
            if (! $purchase->isOpen()) {
                return $purchase;
            }
            $now = CarbonImmutable::now();
            if ($answer->paid()) {
                if ($answer->amount === null || $answer->amount->minor !== $purchase->price_minor || $answer->amount->currency !== $purchase->currency) {
                    // Paid, but not what was asked: nothing is added; the platform team looks (it is reported, never guessed).
                    report(new \RuntimeException('A top-up was paid an amount other than its price: purchase '.$purchase->id));

                    return $purchase;
                }
                $card = $purchase->method === TopUpPurchase::CARD && $purchase->save_card && $answer->card !== null
                    ? $this->cards->keep($purchase->academy_id, $answer->card, $purchase->requested_by) : null;
                $lot = $this->meters->addLot($purchase->academy_id, $purchase->meter(), Ledger::PURCHASE, $purchase->amount, $now->addMonths(Ledger::LOT_MONTHS), [
                    'ref_type' => 'purchase', 'ref_id' => $purchase->id, 'user_id' => $purchase->method === TopUpPurchase::CARD ? $purchase->requested_by : null,
                ]);
                $purchase->update(array_filter(['status' => 'paid', 'paid_at' => $now, 'payment_ref' => $answer->intentId, 'lot_id' => $lot, 'payment_method_id' => $card?->id], fn ($v) => $v !== null));
                $receipt = $this->receipts->issue($purchase);
                $this->audit->record($purchase->academy_id, 'academy.topup_paid', 'topup_purchase', $purchase->id, [
                    'limit' => $purchase->limit, 'amount' => $purchase->amount, 'method' => $purchase->method, 'receipt' => $receipt->number,
                ]);

                return $purchase;
            }
            if ($answer->status === StripePayment::EXPIRED) {
                $purchase->update(['status' => 'expired', 'failed_at' => $now]);
            } elseif ($answer->status === StripePayment::FAILED) {
                $declined = $answer->decline ?? CardDecline::Declined;
                $purchase->update(['status' => 'failed', 'failed_at' => $now, 'failure' => $declined->value, 'payment_ref' => $answer->intentId]);
            }

            return $purchase;
        });

        if ($receipt !== null) {
            PlanRequests::forget($settled->academy_id);
            if ($settled->method !== TopUpPurchase::TRANSFER) {
                $this->academies->raise($settled->academy_id, new RechargeSucceeded(
                    $settled->academy_id, $settled->meter()->value, $settled->amount, $settled->price(), $settled->method, $receipt->number, $settled->id,
                ));
            }
        }
        if ($declined !== null && $settled->method === TopUpPurchase::AUTO) {
            app(ChargeAutoTopUp::class)->declined($settled, $declined);
        }

        return $settled;
    }
}
