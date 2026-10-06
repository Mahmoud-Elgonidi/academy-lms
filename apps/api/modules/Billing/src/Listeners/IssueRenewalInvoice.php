<?php

namespace Modules\Billing\Listeners;

use Illuminate\Support\Facades\DB;
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

        if (Invoice::query()->where('subscription_id', $subscription->id)->where('status', 'issued')->exists()) {
            return;
        }

        $subject = DB::table('enrollments as e')->join('subjects as s', fn ($j) => $j->on('s.academy_id', '=', 'e.academy_id')->on('s.id', '=', 'e.subject_id'))
            ->where('e.id', $subscription->enrollment_id)->first(['s.name_ar', 's.name_en']);

        $this->issue->handle($subscription, $subscription->block_no + 1, $subject->name_ar ?? '', $subject->name_en ?? '');
    }
}
