<?php

namespace Modules\Billing\Queries\Renewals;

use Carbon\CarbonImmutable;
use Modules\Billing\Discounts\InvoiceDiscounts;
use Modules\Billing\Domain\Renewals\RenewalPace;
use Modules\Billing\Domain\TaxCalculator;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\Subscription;
use Modules\Credits\Contracts\CreditsApi;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Each course's next block: when its invoice should go out (its paid-for
 * minutes left, less its renewal mark, at its plan's pace: RenewalPace, the
 * reckoning IssueRenewal's family window uses) and what it would come to
 * (its price, its family discount and coupon, VAT), read for many courses
 * at once (CreditsApi::balances). One answer for the revenue forecast, the
 * family's balance and the renewal preview (docs/architecture/02, section
 * 27, J5a), so they never disagree.
 */
final class NextRenewals
{
    public function __construct(
        private readonly CreditsApi $credits,
        private readonly InvoiceDiscounts $discounts,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  iterable<Subscription>  $subscriptions  with their plan
     * @return array<string, NextRenewal> by subscription id; one without a plan or a balance is left out
     */
    public function of(iterable $subscriptions, ?CarbonImmutable $now = null): array
    {
        $subscriptions = collect($subscriptions)->filter(fn (Subscription $s) => $s->plan !== null)->values();
        if ($subscriptions->isEmpty()) {
            return [];
        }
        $balances = $this->credits->balances($subscriptions->pluck('credit_account_id')->map(fn ($id) => (string) $id)->unique()->values()->all());
        $timezone = $this->tenant->require()->defaultTimezone;
        $today = CarbonImmutable::parse(($now ?? CarbonImmutable::now())->setTimezone($timezone)->toDateString(), $timezone);
        $settings = BillingSettings::current();
        $lines = $this->discounts->blockLines($subscriptions->all());

        $out = [];
        foreach ($subscriptions as $subscription) {
            $balance = $balances[$subscription->credit_account_id] ?? null;
            if ($balance === null) {
                continue;
            }
            $plan = $subscription->plan;
            $pace = new RenewalPace($balance->renewableMin(), $plan->terms()->lowBalanceMinutes(), (int) $plan->session_minutes, (int) $plan->sessions_per_week);
            $days = $pace->daysUntilMark();
            $net = $subscription->amount_minor + array_sum(array_column($lines[$subscription->id] ?? [], 'amount_minor'));
            $out[$subscription->id] = new NextRenewal(
                $subscription->id, $pace, $balance, $today->addDays($days), $days,
                Money::of(TaxCalculator::compute(max(0, $net), $settings->tax_rate_bp, $settings->prices_include_tax)['total'], $subscription->currency),
            );
        }

        return $out;
    }
}
