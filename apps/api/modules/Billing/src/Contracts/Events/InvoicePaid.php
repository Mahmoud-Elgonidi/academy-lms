<?php

namespace Modules\Billing\Contracts\Events;

final readonly class InvoicePaid
{
    /** @param  list<string>  $subscriptionIds  the courses it was for (several on a family invoice) */
    public function __construct(public string $academyId, public string $invoiceId, public array $subscriptionIds) {}
}
