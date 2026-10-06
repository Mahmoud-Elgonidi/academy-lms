<?php

namespace Modules\Billing\Contracts\Events;

/** Communication sends the payer the invoice and its pay link (when it lands). */
final readonly class InvoiceIssued
{
    public function __construct(public string $academyId, public string $invoiceId, public string $billingAccountId, public ?string $subscriptionId) {}
}
