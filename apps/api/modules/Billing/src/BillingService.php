<?php

namespace Modules\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\BuyExtraLessons;
use Modules\Billing\Actions\Discounts\ApplyCoupon;
use Modules\Billing\Actions\Discounts\RemoveCoupon;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\SubscribeEnrollment;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\CourseBilling;
use Modules\Billing\Contracts\DTOs\IssuedInvoice;
use Modules\Billing\Contracts\DTOs\NewSubscription;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Billing\Contracts\DTOs\PriceQuote;
use Modules\Billing\Contracts\DTOs\RegionOption;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Models\PriceRegion;
use Modules\Billing\Queries\CourseBillingQuery;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\SharedKernel\Money\Money;

final class BillingService implements BillingApi
{
    public function __construct(
        private readonly SubscribeEnrollment $subscribe,
        private readonly RecordPayment $payments,
        private readonly CourseBillingQuery $courses,
        private readonly EnrollmentApi $enrollments,
    ) {}

    public function subscribe(NewSubscription $new): IssuedInvoice
    {
        $invoice = $this->subscribe->handle($new);

        return new IssuedInvoice($invoice->id, (string) $invoice->number, $invoice->total());
    }

    public function adopt(NewSubscription $new): string
    {
        return $this->subscribe->adopt($new)->id;
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
                names: [$plan->name_ar, $plan->name_en],
            ))
            ->all();
    }

    public function regions(): array
    {
        return PriceRegion::query()->orderByDesc('is_default')->orderBy('sort')->get()
            ->map(fn (PriceRegion $r) => new RegionOption($r->id, $r->name(), $r->currency, $r->is_default))
            ->all();
    }

    public function quote(string $planId, string $priceRegionId, string $payerId, string $studentId, ?string $couponCode = null): ?PriceQuote
    {
        return $this->courses->quote($planId, $priceRegionId, $payerId, $studentId, $couponCode);
    }

    public function courseBilling(string $enrollmentId): ?CourseBilling
    {
        return $this->courses->forEnrollment($enrollmentId, $this->enrollments->find($enrollmentId)?->status->value === 'active');
    }

    public function applyCoupon(string $enrollmentId, string $code, bool $reissue, ?string $actorId): ?string
    {
        return app(ApplyCoupon::class)->handle($this->subscriptionId($enrollmentId), $code, $actorId, $reissue)['reissued']?->number;
    }

    public function removeCoupon(string $enrollmentId, ?string $actorId): void
    {
        app(RemoveCoupon::class)->handle($this->subscriptionId($enrollmentId), $actorId);
    }

    public function sellExtraLessons(string $enrollmentId, int $lessons, ?string $actorId): IssuedInvoice
    {
        $invoice = app(BuyExtraLessons::class)->handle($this->subscriptionId($enrollmentId), $lessons, $actorId);

        return new IssuedInvoice($invoice->id, (string) $invoice->number, $invoice->total());
    }

    private function subscriptionId(string $enrollmentId): string
    {
        return $this->courses->subscriptionOf($enrollmentId)?->id
            ?? throw ValidationException::withMessages(['enrollment' => __('billing::discounts.errors.no_subscription')]);
    }
}
