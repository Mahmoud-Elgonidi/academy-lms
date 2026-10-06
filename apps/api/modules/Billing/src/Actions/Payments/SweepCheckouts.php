<?php

namespace Modules\Billing\Actions\Payments;

use Carbon\CarbonImmutable;
use Modules\Billing\Models\PaymentCheckout;
use Throwable;

/**
 * The safety net for webhooks that never came: checkouts still open past
 * their time are asked of the gateway once more; those it still calls open
 * half an hour later are closed as expired.
 */
final class SweepCheckouts
{
    public const GRACE_MINUTES = 30;

    public function __construct(private readonly SettleCheckout $settle) {}

    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $changed = 0;

        PaymentCheckout::query()->where('status', 'open')->where('expires_at', '<', $now)->orderBy('expires_at')->limit(200)->get()
            ->each(function (PaymentCheckout $checkout) use ($now, &$changed) {
                try {
                    $after = $this->settle->handle($checkout);
                } catch (Throwable $e) {
                    report($e);
                    $after = $checkout;
                }
                if ($after->status === 'open' && ($after->provider_ref === null || $after->expires_at->addMinutes(self::GRACE_MINUTES)->lessThan($now))) {
                    $after->update(['status' => 'expired']);
                }
                $changed += $after->status !== 'open' ? 1 : 0;
            });

        return $changed;
    }
}
