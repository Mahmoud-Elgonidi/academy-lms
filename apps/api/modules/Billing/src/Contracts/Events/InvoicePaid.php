<?php

namespace Modules\Billing\Contracts\Events;

final readonly class InvoicePaid
{
    public function __construct(public string $academyId, public string $invoiceId, public ?string $subscriptionId) {}
}
