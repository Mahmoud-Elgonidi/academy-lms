<?php

namespace Modules\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\BuyExtraLessons;
use Modules\Billing\Actions\CloseEndedCourse;
use Modules\Billing\Actions\Discounts\ApplyCoupon;
use Modules\Billing\Actions\Discounts\RemoveCoupon;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\SavePlan;
use Modules\Billing\Actions\SubscribeEnrollment;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\CourseBilling;
use Modules\Billing\Contracts\DTOs\EndedCourseBilling;
use Modules\Billing\Contracts\DTOs\IssuedInvoice;
use Modules\Billing\Contracts\DTOs\NewPlan;
use Modules\Billing\Contracts\DTOs\NewSubscription;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Billing\Contracts\DTOs\PriceQuote;
use Modules\Billing\Contracts\DTOs\RegionOption;
use Modules\Billing\Contracts\DTOs\SharedPackage;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Models\PriceRegion;
use Modules\Billing\Models\Subscription;
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
                shared: $plan->isShared(),
            ))
            ->all();
    }

    public function createPlan(NewPlan $plan): string
    {
        return app(SavePlan::class)->handle(null, [
            'name_ar' => $plan->nameAr,
            'name_en' => $plan->nameEn,
            'session_minutes' => $plan->sessionMinutes,
            'sessions_per_week' => $plan->sessionsPerWeek,
            'sessions_per_block' => $plan->sessionsPerBlock,
            'prices' => collect($plan->prices)->map(fn (string $amount, string $region) => ['price_region_id' => $region, 'amount' => $amount])->values()->all(),
        ])->id;
    }

    public function sharedPackage(string $studentId, string $planId, string $payerId): ?SharedPackage
    {
        $subscription = Subscription::query()
            ->join('billing_accounts as ba', fn ($j) => $j->on('ba.academy_id', '=', 'subscriptions.academy_id')->on('ba.id', '=', 'subscriptions.billing_account_id'))
            ->join('enrollments as e', fn ($j) => $j->on('e.academy_id', '=', 'subscriptions.academy_id')->on('e.id', '=', 'subscriptions.enrollment_id'))
            ->where('subscriptions.plan_id', $planId)->where('subscriptions.status', '<>', 'cancelled')
            ->where('ba.payer_id', $payerId)->where('e.student_id', $studentId)
            ->whereExists(fn ($q) => $q->from('plans as p')->whereColumn('p.academy_id', 'subscriptions.academy_id')->whereColumn('p.id', 'subscriptions.plan_id')->where('p.credit_scope', Plan::SHARED))
            ->latest('subscriptions.created_at')
            ->first(['subscriptions.*']);
        if ($subscription === null) {
            return null;
        }

        return new SharedPackage(
            subscriptionId: $subscription->id,
            creditAccountId: $subscription->credit_account_id,
            paid: $subscription->block_no >= 1,
            subjectIds: DB::table('enrollments')->where('credit_account_id', $subscription->credit_account_id)->where('status', '<>', 'ended')
                ->distinct()->pluck('subject_id')->map(fn ($id) => (string) $id)->all(),
        );
    }

    public function regions(): array
    {
        return PriceRegion::query()->orderByDesc('is_default')->orderBy('sort')->get()
            ->map(fn (PriceRegion $r) => new RegionOption($r->id, $r->name(), $r->currency, $r->is_default, array_values($r->countries ?? [])))
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

    public function endingCourse(string $enrollmentId): EndedCourseBilling
    {
        return app(CloseEndedCourse::class)->preview($enrollmentId);
    }

    public function closeEndedCourse(string $enrollmentId, ?string $actorId): EndedCourseBilling
    {
        return app(CloseEndedCourse::class)->handle($enrollmentId, $actorId);
    }

    private function subscriptionId(string $enrollmentId): string
    {
        return $this->courses->subscriptionOf($enrollmentId)?->id
            ?? throw ValidationException::withMessages(['enrollment' => __('billing::discounts.errors.no_subscription')]);
    }
}
