<?php

namespace Modules\Billing\Payments;

use Illuminate\Http\Request;
use Modules\SharedKernel\Money\Money;

/**
 * A payment gateway, on the academy's own account there
 * (docs/architecture/03, section 5). Hosted pages only: the card never
 * touches the platform (PCI SAQ A). The gateway's word is the truth: a
 * checkout counts as paid only when fetch() says so, whatever a webhook or
 * a return URL claims.
 */
interface PaymentGateway
{
    /** Null when this amount can be paid here, else why not (a currency it does not take, an amount it cannot charge). */
    public function refuses(Money $amount): ?string;

    /** Opens the gateway's page for one checkout. @throws GatewayException */
    public function createCheckout(CheckoutRequest $request): CheckoutSession;

    /** Where a checkout stands, asked of the gateway itself. @throws GatewayException */
    public function fetch(string $providerRef): CheckoutState;

    /** A webhook delivery, verified; null when it is not genuine (wrong signature or secret). */
    public function parseWebhook(Request $request): ?GatewayEvent;

    /** Null when the keys work, else what the gateway said. */
    public function check(): ?string;
}
