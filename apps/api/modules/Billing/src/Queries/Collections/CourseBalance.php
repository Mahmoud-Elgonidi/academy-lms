<?php

namespace Modules\Billing\Queries\Collections;

use Carbon\CarbonImmutable;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Queries\Renewals\NextRenewal;
use Modules\Billing\Queries\Renewals\NextRenewals;
use Modules\Credits\Contracts\CreditsApi;
use Modules\Credits\Contracts\DTOs\CreditBalance;
use Modules\Enrollment\Contracts\DTOs\EnrollmentData;
use Modules\Enrollment\Contracts\EnrollmentStatus;

/**
 * A course's balance as the family reads it (docs/architecture/02, section 27,
 * J5a): its plan and shape, the paid-for lessons left, when they expire
 * (a warning CreditsApi::EXPIRY_NOTICE_DAYS before), and its next invoice: the
 * block waiting to be paid, or when the next one goes out and about how much
 * (Renewals\NextRenewals, the forecast's reckoning; none while the course is
 * paused, waiting for its first payment, or ending).
 */
final class CourseBalance
{
    public function __construct(private readonly CreditsApi $credits, private readonly NextRenewals $renewals) {}

    /**
     * @return array{subscription: ?Subscription, plan: ?Plan, balance: ?CreditBalance, next: ?NextRenewal, waiting: ?Invoice, renews: bool, expiry_soon: bool}
     */
    public function of(EnrollmentData $course, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $subscription = Subscription::ofEnrollment($course->id);
        $balance = $course->creditAccountId === null ? null : $this->credits->balance($course->creditAccountId);
        $waiting = $subscription === null ? null : Invoice::query()->forSubscription($subscription->id, 'block')->where('status', 'issued')->orderBy('issued_at')->first();
        $renews = $subscription !== null && $subscription->status === 'active' && $course->status === EnrollmentStatus::Active && $course->endsOn === null && $subscription->block_no >= 1;
        $next = $renews && $waiting === null ? ($this->renewals->of([$subscription], $now)[$subscription->id] ?? null) : null;

        return [
            'subscription' => $subscription,
            'plan' => $subscription?->plan,
            'balance' => $balance,
            'next' => $next,
            'waiting' => $waiting,
            'renews' => $renews,
            'expiry_soon' => $balance?->nextExpiry !== null && $balance->expiringMin > 0
                && $balance->nextExpiry->lessThanOrEqualTo($now->addDays(CreditsApi::EXPIRY_NOTICE_DAYS)),
        ];
    }
}
