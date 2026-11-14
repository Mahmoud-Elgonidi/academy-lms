<?php

namespace Modules\Platform\Recharging;

use Carbon\CarbonImmutable;
use Modules\Platform\Actions\Recharging\ChargeAutoTopUp;
use Modules\Platform\Actions\Recharging\SettleTopUpPayment;
use Modules\Platform\Actions\Recharging\StartTopUpCheckout;
use Modules\Platform\Models\Recharging\TopUpPurchase;
use Modules\Platform\Payments\StripeClient;
use Modules\SharedKernel\Plans\Meter;
use Throwable;

/**
 * Each hour, with the usage ledger (platform:metering), for one academy:
 * Checkout pages left open past their time are asked about (a webhook that
 * never came), and each meter's automatic recharge is seen to
 * (ChargeAutoTopUp). One that fails is reported and the rest go on.
 */
final class RechargeSweep
{
    public function __construct(private readonly SettleTopUpPayment $settle, private readonly ChargeAutoTopUp $charge) {}

    public function academy(string $academyId, ?CarbonImmutable $at = null): void
    {
        if (! StripeClient::configured()) {
            return;
        }
        $at ??= CarbonImmutable::now();
        $stale = TopUpPurchase::query()->where('academy_id', $academyId)->where('method', TopUpPurchase::CARD)->where('status', 'open')
            ->whereNotNull('provider_ref')->where('created_at', '<=', $at->subMinutes(StartTopUpCheckout::OPEN_MINUTES + 5))->limit(20)->get();
        foreach ($stale as $purchase) {
            $this->quietly(fn () => $this->settle->handle($purchase));
        }
        foreach (Meter::cases() as $meter) {
            $this->quietly(fn () => $this->charge->handle($academyId, $meter, $at));
        }
    }

    private function quietly(callable $work): void
    {
        try {
            $work();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
