<?php

namespace Modules\Billing\Contracts\Events;

/** A payer sent proof of paying an invoice by hand; it waits for the academy. */
final readonly class PaymentProofSubmitted
{
    public function __construct(public string $academyId, public string $proofId, public string $invoiceId) {}
}
