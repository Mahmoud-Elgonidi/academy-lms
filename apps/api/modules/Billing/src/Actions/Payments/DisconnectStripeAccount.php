<?php

namespace Modules\Billing\Actions\Payments;

use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Payments\GatewayException;
use Modules\Billing\Payments\StripeConnect;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Lets the academy's connected Stripe account go: Stripe stops the platform
 * charging on it, and it is turned off here (kept, for the payments made
 * through it). If Stripe says it was already let go, it is turned off all
 * the same.
 */
final class DisconnectStripeAccount
{
    public function __construct(private readonly StripeConnect $connect) {}

    public function handle(GatewayAccount $account): GatewayAccount
    {
        if ($account->gateway !== 'stripe_connect' || $account->external_id === null) {
            throw new GatewayException('Only a connected Stripe account can be disconnected.');
        }
        try {
            $this->connect->deauthorize($account->external_id);
        } catch (GatewayException $e) {
            if (! str_contains($e->getMessage(), 'not connected') && ! str_contains($e->getMessage(), 'invalid_client')) {
                throw $e;
            }
        }
        $account->update(['is_active' => false]);
        app(AuditTrail::class)->record('billing.stripe_disconnected', 'gateway_account', $account->id, ['account' => $account->external_id]);

        return $account;
    }
}
