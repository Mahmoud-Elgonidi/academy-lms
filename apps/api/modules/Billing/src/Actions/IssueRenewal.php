<?php

namespace Modules\Billing\Actions;

use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Subscription;
use Modules\Credits\Contracts\CreditsApi;

/**
 * A course's next block when its balance gets down to its mark
 * (docs/architecture/02, "التجديد بالبلوك"), and on the same invoice the
 * next blocks of the payer's other courses that would come due within the
 * academy's family window (family_invoice_days, a week by default): a
 * course comes due when its paid-for minutes left reach its mark, so it is
 * taken along when they would within the lessons of that many days at its
 * plan's pace. A family then pays once for the children's renewals of that
 * week instead of once per child. A course with a block already waiting is
 * left as it is; nothing already issued changes.
 */
final class IssueRenewal
{
    public function __construct(private readonly IssueBlockInvoice $issue, private readonly CreditsApi $credits) {}

    /** The renewal invoice, or null when the course's next block is already waiting. */
    public function handle(Subscription $due, ?string $actorId = null): ?Invoice
    {
        if ($due->hasWaitingBlock()) {
            return null;
        }

        return $this->issue->together(array_map(fn (Subscription $s) => $this->nextBlock($s), [$due, ...$this->dueWith($due)]), $actorId);
    }

    /** @return list<Subscription> the payer's other courses whose renewal falls within the family window */
    public function dueWith(Subscription $due): array
    {
        $days = BillingSettings::current()->family_invoice_days;
        if ($days <= 0) {
            return [];
        }

        return Subscription::query()->with('plan')
            ->where('billing_account_id', $due->billing_account_id)
            ->where('currency', $due->currency)
            ->where('id', '<>', $due->id)
            ->where('status', 'active')
            ->where('block_no', '>=', 1)
            ->whereExists(fn ($q) => $q->from('enrollments as e')->whereColumn('e.academy_id', 'subscriptions.academy_id')
                ->whereColumn('e.credit_account_id', 'subscriptions.credit_account_id')->where('e.status', 'active'))
            ->orderBy('created_at')->orderBy('id')
            ->get()
            ->filter(fn (Subscription $s) => ! $s->hasWaitingBlock() && $this->comesDueWithin($s, $days))
            ->values()
            ->all();
    }

    private function comesDueWithin(Subscription $subscription, int $days): bool
    {
        $plan = $subscription->plan;
        $ahead = (int) ceil($plan->sessions_per_week * $days / 7) * $plan->session_minutes;

        return $this->credits->balance($subscription->credit_account_id)->renewableMin() <= $plan->terms()->lowBalanceMinutes() + $ahead;
    }

    /** @return array{0: Subscription, 1: int, 2: string, 3: string} */
    private function nextBlock(Subscription $subscription): array
    {
        [$ar, $en] = $subscription->subjectNames();

        return [$subscription, $subscription->block_no + 1, $ar, $en];
    }
}
