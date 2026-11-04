<?php

namespace Modules\Billing\Payments;

use Illuminate\Http\Request;
use Modules\Billing\Payments\Cards\ChargeResult;
use Modules\Billing\Payments\Cards\SavedCard;
use Modules\Billing\Payments\Cards\SavedCharge;
use Modules\SharedKernel\Money\Money;

/**
 * A payment gateway, on the academy's own account there
 * (docs/architecture/03, section 5). Hosted pages only: the card never
 * touches the platform (PCI SAQ A). The gateway's word is the truth: a
 * checkout counts as paid only when fetch() says so, whatever a webhook or
 * a return URL claims.
 *
 * Saved cards (docs/architecture/02, section 27, J5b): a gateway that keeps
 * cards does it on its own page, when the checkout asked (CheckoutRequest
 * saveCard); the card's references come from the gateway's own answer after
 * the payment (savedCard), and a renewal is charged on them with no one at a
 * page (chargeSaved), then asked like any checkout (fetchSaved).
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

    /** Whether this gateway keeps cards for later charges here (Paymob: not offered). */
    public function savesCards(): bool;

    /**
     * The card kept with a paid checkout that asked to save it, from the gateway's own records; null when it kept none.
     *
     * @throws GatewayException
     */
    public function savedCard(string $providerRef): ?SavedCard;

    /** Charges a saved card once, merchant-initiated (never retried here; a network error is "unknown"). */
    public function chargeSaved(SavedCharge $charge): ChargeResult;

    /** Where a charge on a saved card stands, asked of the gateway itself. @throws GatewayException */
    public function fetchSaved(string $ref): CheckoutState;

    /**
     * The gateway's reference of a charge on a saved card we never heard back about (the network failed), found by
     * our checkout's id; null when it has none (or this gateway cannot look it up).
     *
     * @throws GatewayException
     */
    public function findSaved(string $checkoutId): ?string;
}
