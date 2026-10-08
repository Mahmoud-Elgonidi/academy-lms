<?php

namespace Modules\Billing\Listeners;

use Modules\Billing\Actions\IssueBlockInvoice;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Subscription;
use Modules\Credits\Contracts\Events\CreditBalanceLow;

/**
 * Block renewal (docs/architecture/02, "التجديد بالبلوك"): when an
 * enrollment's credit gets down to its mark, the invoice for the next block
 * goes out, unless one is already waiting to be paid.
 */
final class IssueRenewalInvoice
{
    public function __construct(private readonly IssueBlockInvoice $issue) {}

    public function handle(CreditBalanceLow $event): void
    {
        $subscription = Subscription::query()->where('credit_account_id', $event->accountId)->where('status', 'active')->first();
        if ($subscription === null) {
            return;
        }

        // An invoice for extra lessons waiting to be paid does not hold the next block back.
        if (Invoice::query()->where('subscription_id', $subscription->id)->where('status', 'issued')->whereHas('lines', fn ($q) => $q->where('kind', 'block'))->exists()) {
            return;
        }

        [$ar, $en] = $subscription->subjectNames();
        $this->issue->handle($subscription, $subscription->block_no + 1, $ar, $en);
    }
}
