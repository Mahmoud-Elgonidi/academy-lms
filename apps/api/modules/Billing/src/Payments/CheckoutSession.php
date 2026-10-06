<?php

namespace Modules\Billing\Payments;

/** The gateway's page for a checkout: its reference there, and where to send the payer. */
final readonly class CheckoutSession
{
    public function __construct(public string $ref, public string $url) {}
}
