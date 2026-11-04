<?php

namespace Modules\Billing\Actions\Cards;

use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Queries\Collections\PayerReach;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\NotificationsApi;

/**
 * The academy turned a gateway account off (or disconnected its Stripe
 * account): the cards saved on it can no longer be charged, so they are let
 * go (gateway_off), automatic renewal goes off for a payer left with none,
 * and each payer hears it once (billing.card_stopped), with their invoices
 * still payable through the pay link.
 */
final class StopGatewayCards
{
    public function __construct(
        private readonly RevokePaymentMethod $revoke,
        private readonly NotificationsApi $notifications,
        private readonly PayerReach $reach,
    ) {}

    public function handle(GatewayAccount $account): int
    {
        $count = 0;
        foreach (PaymentMethod::query()->live()->where('gateway_account_id', $account->id)->get() as $method) {
            $this->revoke->handle($method->id, $method->payer_id, 'gateway_off');
            $this->notifications->send(new Message('billing.card_stopped', $this->reach->recipients($method->payer_id), [
                'card' => $method->label(),
            ], ['screen' => 'cards', 'id' => $method->payer_id], "card-stopped:{$method->id}"));
            $count++;
        }

        return $count;
    }
}
