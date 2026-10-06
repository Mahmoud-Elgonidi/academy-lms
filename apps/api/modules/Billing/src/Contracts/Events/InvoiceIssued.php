<?php

namespace Modules\Billing\Contracts\Events;

/** Billing tells the payer through Communication; the pay link comes with online payments. */
final readonly class InvoiceIssued
{
    public function __construct(public string $academyId, public string $invoiceId, public string $billingAccountId, public ?string $subscriptionId) {}
}
