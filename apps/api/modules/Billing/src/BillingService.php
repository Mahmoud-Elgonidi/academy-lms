<?php

namespace Modules\Billing;

use Carbon\CarbonImmutable;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\SubscribeEnrollment;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\IssuedInvoice;
use Modules\Billing\Contracts\DTOs\NewSubscription;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Billing\Contracts\DTOs\RegionOption;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Models\PriceRegion;
use Modules\SharedKernel\Money\Money;

final class BillingService implements BillingApi
{
    public function __construct(
        private readonly SubscribeEnrollment $subscribe,
        private readonly RecordPayment $payments,
    ) {}

    public function subscribe(NewSubscription $new): IssuedInvoice
    {
        $invoice = $this->subscribe->handle($new);

        return new IssuedInvoice($invoice->id, (string) $invoice->number, $invoice->total());
    }

    public function recordPayment(string $invoiceId, string $method, Money $amount, ?string $reference, CarbonImmutable $receivedAt, ?string $actorId): void
    {
        $this->payments->handle($invoiceId, $method, $amount, $reference, $receivedAt, null, $actorId);
    }

    public function planOptions(?int $sessionMinutes = null): array
    {
        return Plan::query()->where('is_active', true)->with('prices')
            ->orderByRaw('session_minutes = ? desc', [$sessionMinutes ?? 0])
            ->orderBy('sort')->orderBy('session_minutes')->orderBy('sessions_per_week')->orderBy('sessions_per_block')
            ->get()
            ->map(fn (Plan $plan) => new PlanOption(
                id: $plan->id,
                name: $plan->name(),
                sessionMinutes: $plan->session_minutes,
                sessionsPerBlock: $plan->sessions_per_block,
                sessionsPerWeek: $plan->sessions_per_week,
                validityDays: $plan->validity_days,
                prices: $plan->prices->mapWithKeys(fn (PlanPrice $p) => [$p->price_region_id => $p->money()])->all(),
            ))
            ->all();
    }

    public function regions(): array
    {
        return PriceRegion::query()->orderByDesc('is_default')->orderBy('sort')->get()
            ->map(fn (PriceRegion $r) => new RegionOption($r->id, $r->name(), $r->currency, $r->is_default))
            ->all();
    }
}
