<?php

namespace Modules\Billing\Contracts;

use Carbon\CarbonImmutable;
use Modules\Billing\Contracts\DTOs\IssuedInvoice;
use Modules\Billing\Contracts\DTOs\NewSubscription;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Billing\Contracts\DTOs\RegionOption;
use Modules\SharedKernel\Money\Money;

/** What other modules may ask of Billing. */
interface BillingApi
{
    /**
     * Puts an enrollment on a plan and issues the invoice for its first
     * block. Paying it grants the block's minutes to the credit account.
     */
    public function subscribe(NewSubscription $new): IssuedInvoice;

    /**
     * Records money received for an invoice (a transfer, cash, a wallet). Paid
     * in full, it grants the block's minutes in the same transaction.
     */
    public function recordPayment(string $invoiceId, string $method, Money $amount, ?string $reference, CarbonImmutable $receivedAt, ?string $actorId): void;

    /** @return list<PlanOption> active plans, those with this session length first */
    public function planOptions(?int $sessionMinutes = null): array;

    /** @return list<RegionOption> */
    public function regions(): array;
}
