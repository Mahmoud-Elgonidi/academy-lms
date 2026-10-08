<?php

namespace Modules\Billing\Contracts\Events;

/** The academy approved a payer's proof (the payment is recorded) or turned it down with a reason. */
final readonly class PaymentProofDecided
{
    public function __construct(public string $academyId, public string $proofId, public string $invoiceId, public bool $approved) {}
}
