<?php

namespace Modules\Billing\Contracts\Events;

/** Billing tells the payer through Communication; the pay link comes with online payments. */
final readonly class InvoiceIssued
{
    /** @param  list<string>  $subscriptionIds  the courses it is for (several on a family invoice) */
    public function __construct(public string $academyId, public string $invoiceId, public string $billingAccountId, public array $subscriptionIds) {}
}
