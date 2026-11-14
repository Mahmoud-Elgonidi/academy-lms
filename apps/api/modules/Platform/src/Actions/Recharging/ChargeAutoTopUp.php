<?php

namespace Modules\Platform\Actions\Recharging;

use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Domain\Recharging\AutoRechargeRules;
use Modules\Platform\Metering\Meters;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\Recharging\AutoRecharge;
use Modules\Platform\Models\Recharging\PlatformPaymentMethod;
use Modules\Platform\Models\Recharging\TopUpPurchase;
use Modules\Platform\Payments\CardDecline;
use Modules\Platform\Payments\StripeCharge;
use Modules\Platform\Payments\StripeClient;
use Modules\Platform\Recharging\InAcademy;
use Modules\Platform\Recharging\RechargeStatus;
use Modules\SharedKernel\Plans\Events\AutoRechargeTurnedOff;
use Modules\SharedKernel\Plans\Events\RechargeFailed;
use Modules\SharedKernel\Plans\Meter;

/**
 * Automatic recharge, Twilio's way (docs/architecture/02, section 11, "الشحن
 * التلقائي"), from the hourly platform:metering: while a meter's balance is
 * below the academy's threshold, the pack is bought on the saved card with no
 * one present, at most monthly_cap times a calendar month on the academy's
 * clock (Domain\Recharging\AutoRechargeRules). Each attempt is a purchase
 * (method auto) charged with the Idempotency-Key "auto-{purchase}", so an
 * unclear answer is asked again with the same key and never charged twice;
 * its money counts only through SettleTopUpPayment. A soft decline is tried
 * once more 24 hours later; a hard one, or the retry declined too, turns
 * automatic recharge off (a card that is done is let go too) and the staff
 * who see the plan hear of it. No card, or no price in the academy's
 * currency, turns it off as well.
 */
final class ChargeAutoTopUp
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly Meters $meters,
        private readonly InAcademy $academies,
        private readonly PlatformAudit $audit,
    ) {}

    /** @return string what was done (AutoRechargeRules' word, or off) */
    public function handle(string $academyId, Meter $meter, ?CarbonImmutable $at = null): string
    {
        $at ??= CarbonImmutable::now();
        $setting = AutoRecharge::for($academyId, $meter);
        if ($setting === null || ! $setting->enabled || ! StripeClient::configured() || $this->meters->limitOf($academyId, $meter) === null) {
            return AutoRechargeRules::NOTHING;
        }
        $last = RechargeStatus::lastAuto($academyId, $meter);
        if ($last !== null && $last->isOpen()) {
            $this->resume($last);

            return AutoRechargeRules::WAIT;
        }
        $state = $this->meters->state($academyId, $meter, $at);
        $retried = $last !== null && TopUpPurchase::query()->where('retry_of', $last->id)->exists();
        $decision = AutoRechargeRules::next(
            $state->available(),
            $setting->threshold,
            RechargeStatus::boughtThisMonth($academyId, $meter, $this->meters->month($academyId, $at)),
            $setting->monthly_cap,
            $last === null ? null : [
                'status' => $last->status, 'attempt' => $last->attempt, 'soft' => $last->failure !== null && (CardDecline::tryFrom($last->failure)?->soft() ?? false),
                'failed_at' => $last->failed_at, 'retried' => $retried,
            ],
            $at,
        );
        if (! in_array($decision, [AutoRechargeRules::BUY, AutoRechargeRules::RETRY], true)) {
            return $decision;
        }

        $academy = Academy::query()->findOrFail($academyId);
        $card = PlatformPaymentMethod::liveFor($academyId);
        if ($card === null || $card->expired($at)) {
            $this->turnOff($setting, 'no_card');

            return 'off';
        }
        $pack = $setting->pack;
        $price = $pack?->priceIn($academy->base_currency);
        if ($pack === null || $pack->archived_at !== null || $price === null) {
            $this->turnOff($setting, 'no_price');

            return 'off';
        }

        try {
            $purchase = TopUpPurchase::query()->create([
                'academy_id' => $academyId, 'pack_id' => $pack->id, 'limit' => $pack->limit, 'amount' => $pack->amount,
                'price_minor' => $price->minor, 'currency' => $price->currency, 'method' => TopUpPurchase::AUTO,
                'payment_method_id' => $card->id, 'attempt' => $decision === AutoRechargeRules::RETRY ? 2 : 1,
                'retry_of' => $decision === AutoRechargeRules::RETRY ? $last?->id : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            return AutoRechargeRules::WAIT;   // another run took it
        }
        $this->charge($purchase, $card);

        return $decision;
    }

    /** A declined automatic purchase (from the charge, or as Stripe told us later): tried again once, else off. */
    public function declined(TopUpPurchase $purchase, CardDecline $decline): void
    {
        $setting = AutoRecharge::for($purchase->academy_id, $purchase->meter());
        $staysOn = AutoRechargeRules::staysOn($purchase->attempt, $decline->soft());
        $this->academies->raise($purchase->academy_id, new RechargeFailed($purchase->academy_id, $purchase->meter()->value, $decline->value, $staysOn, $purchase->id));
        if ($decline->endsCard() && $purchase->payment_method_id !== null) {
            $now = CarbonImmutable::now();
            PlatformPaymentMethod::query()->whereKey($purchase->payment_method_id)->whereNull('removed_at')->update(['removed_at' => $now, 'removed_reason' => 'declined', 'updated_at' => $now]);
        }
        if (! $staysOn && $setting !== null && $setting->enabled) {
            $this->turnOff($setting, 'declined');
        }
    }

    /** An attempt with no clear answer yet: the same request again (same key), or Stripe asked about it. */
    private function resume(TopUpPurchase $purchase): void
    {
        if ($purchase->provider_ref !== null) {
            app(SettleTopUpPayment::class)->handle($purchase);

            return;
        }
        $card = $purchase->payment_method_id === null ? null : PlatformPaymentMethod::query()->find($purchase->payment_method_id);
        if ($card === null || $card->removed_at !== null) {
            $purchase->update(['status' => 'failed', 'failed_at' => now(), 'failure' => CardDecline::InvalidCard->value]);

            return;
        }
        $this->charge($purchase, $card);
    }

    private function charge(TopUpPurchase $purchase, PlatformPaymentMethod $card): void
    {
        $result = $this->stripe->chargeSaved(
            'auto-'.$purchase->id,
            $card->customer_ref,
            $card->method_ref,
            $purchase->price(),
            ($purchase->pack?->name('en') ?? 'Pack').' · '.__('shared::plans.limits.'.$purchase->limit, [], 'en').' +'.number_format($purchase->amount),
            ['academy' => $purchase->academy_id, 'pack' => (string) $purchase->pack_id, 'purchase' => $purchase->id],
        );
        match ($result->status) {
            StripeCharge::PAID, StripeCharge::PENDING => $this->settle($purchase, (string) $result->ref),
            StripeCharge::FAILED => $this->fail($purchase, $result),
            default => null,    // unknown: asked again on the next run, with the same key
        };
    }

    private function settle(TopUpPurchase $purchase, string $ref): void
    {
        if ($ref === '') {
            return;
        }
        $purchase->update(['provider_ref' => $ref]);
        app(SettleTopUpPayment::class)->handle($purchase);
    }

    private function fail(TopUpPurchase $purchase, StripeCharge $result): void
    {
        $decline = $result->decline ?? CardDecline::Declined;
        $purchase->update(array_filter([
            'status' => 'failed', 'failed_at' => now(), 'failure' => $decline->value, 'provider_ref' => $result->ref,
        ], fn ($v) => $v !== null));
        $this->declined($purchase->fresh() ?? $purchase, $decline);
    }

    private function turnOff(AutoRecharge $setting, string $reason): void
    {
        DB::connection('pgsql_platform')->transaction(function () use ($setting, $reason): void {
            $setting->update(['enabled' => false, 'off_reason' => $reason, 'off_at' => now()]);
            $this->audit->record($setting->academy_id, 'academy.auto_recharge_off', 'auto_recharge', null, ['meter' => $setting->meter, 'off_reason' => $reason]);
        });
        $this->academies->raise($setting->academy_id, new AutoRechargeTurnedOff($setting->academy_id, $setting->meter, $reason));
    }
}
