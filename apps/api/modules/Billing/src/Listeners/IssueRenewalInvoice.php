<?php

namespace Modules\Billing\Listeners;

use Modules\Billing\Actions\IssueRenewal;
use Modules\Billing\Models\Subscription;
use Modules\Credits\Contracts\Events\CreditBalanceLow;

/**
 * Block renewal (docs/architecture/02, "التجديد بالبلوك"): when a course's
 * credit gets down to its mark, the invoice for the next block goes out
 * (with the family's other renewals due that week), unless one is already
 * waiting to be paid. An invoice for extra lessons waiting does not hold it back.
 */
final class IssueRenewalInvoice
{
    public function __construct(private readonly IssueRenewal $renew) {}

    public function handle(CreditBalanceLow $event): void
    {
        $subscription = Subscription::query()->with('plan')->where('credit_account_id', $event->accountId)->where('status', 'active')->first();
        if ($subscription !== null) {
            $this->renew->handle($subscription);
        }
    }
}
